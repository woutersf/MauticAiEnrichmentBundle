<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAiEnrichmentBundle\Service;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\MauticAIconnectionBundle\Service\LiteLLMService;
use MauticPlugin\MauticAiLogBundle\Service\AiLogger;
use Psr\Log\LoggerInterface;

class EnrichmentService
{
    public function __construct(
        private LiteLLMService $liteLLMService,
        private WebFetcherService $webFetcherService,
        private EnrichmentTypeRegistry $typeRegistry,
        private CoreParametersHelper $coreParametersHelper,
        private LoggerInterface $logger,
        private AiLogger $aiLogger
    ) {
    }

    /**
     * Main enrichment method using nested AI calls
     *
     * @param string $companyName
     * @param string $enrichmentType
     * @param int|null $companyId
     * @return array
     * @throws \Exception
     */
    public function enrich(string $companyName, string $enrichmentType, ?int $companyId = null): array
    {
        $this->logger->info('AI Enrichment: Starting enrichment', [
            'company_name'     => $companyName,
            'enrichment_type'  => $enrichmentType,
            'company_id'       => $companyId,
        ]);

        // Validate enrichment type
        if (!$this->typeRegistry->hasType($enrichmentType)) {
            throw new \Exception("Invalid enrichment type: $enrichmentType");
        }

        $typeConfig = $this->typeRegistry->getType($enrichmentType);

        // Build user prompt from type config
        $userPrompt = str_replace('{company_name}', $companyName, $typeConfig['prompt']);

        // Step 1: Build initial messages for Assistant AI (Level 1)
        $messages = [
            [
                'role'    => 'system',
                'content' => $this->getAssistantSystemPrompt(),
            ],
            [
                'role'    => 'user',
                'content' => $userPrompt,
            ],
        ];

        // Step 2: Define web_search tool
        $tools = [$this->getWebSearchTool()];

        // Step 3: Call Assistant AI in a loop (max 7 iterations)
        $maxIterations = 7;
        $iteration     = 0;

        while ($iteration < $maxIterations) {
            $this->logger->debug('AI Enrichment: Iteration ' . ($iteration + 1), [
                'messages_count' => count($messages),
            ]);

            // Log AI request to dedicated log file
            $this->aiLogger->logRequest($companyName, $enrichmentType, $messages);

            // Call Assistant AI with tools
            $response = $this->liteLLMService->getChatCompletion($messages, [
                'tools'       => $tools,
                'tool_choice' => 'auto',
                'temperature' => 0.3,
                'model'       => $this->getModel(),
            ]);

            // Log AI response to dedicated log file
            $this->aiLogger->logResponse($companyName, $enrichmentType, $response, $iteration + 1);

            $assistantMessage = $response['choices'][0]['message'];

            // Add assistant's response to conversation
            $messages[] = $assistantMessage;

            // Check if assistant wants to call a tool
            if (isset($assistantMessage['tool_calls']) && !empty($assistantMessage['tool_calls'])) {
                $this->logger->debug('AI Enrichment: Assistant requested tool calls', [
                    'tool_calls_count' => count($assistantMessage['tool_calls']),
                ]);

                // Execute each tool call
                foreach ($assistantMessage['tool_calls'] as $toolCall) {
                    $toolResult = $this->executeToolCall($toolCall, $enrichmentType);

                    // Add tool result to conversation
                    $messages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'content'      => json_encode($toolResult),
                    ];
                }

                $iteration++;
                continue; // Loop back to get next AI response
            }

            // No more tool calls, we have the final answer
            $finalAnswer = $assistantMessage['content'] ?? 'No answer found';

            $this->logger->info('AI Enrichment: Completed successfully', [
                'company_name'    => $companyName,
                'enrichment_type' => $enrichmentType,
                'iterations'      => $iteration,
                'result'          => substr($finalAnswer, 0, 200),
            ]);

            // Log final result to dedicated log file
            $this->aiLogger->logResult($companyName, $enrichmentType, $finalAnswer, $iteration);

            return [
                'success'    => true,
                'result'     => $finalAnswer,
                'iterations' => $iteration,
            ];
        }

        // Max iterations reached
        $this->logger->warning('AI Enrichment: Max iterations reached', [
            'company_name'    => $companyName,
            'enrichment_type' => $enrichmentType,
        ]);

        $error = 'Max iterations reached without finding answer. Please try again.';
        $this->aiLogger->logError($companyName, $enrichmentType, $error, ['iterations' => $maxIterations]);

        throw new \Exception($error);
    }

    /**
     * Execute a tool call (web_search)
     *
     * @param array $toolCall
     * @param string $enrichmentType
     * @return array
     */
    private function executeToolCall(array $toolCall, string $enrichmentType): array
    {
        $functionName = $toolCall['function']['name'];
        $arguments    = json_decode($toolCall['function']['arguments'], true);

        $this->logger->debug('AI Enrichment: Executing tool call', [
            'function' => $functionName,
            'arguments' => $arguments,
        ]);

        if ($functionName === 'web_search') {
            $url = $arguments['url'] ?? '';
            $reason = $arguments['reason'] ?? 'Not specified';

            if (empty($url)) {
                return [
                    'error'   => 'URL is required',
                    'success' => false,
                ];
            }

            // Validate URL
            if (!$this->webFetcherService->isValidUrl($url)) {
                return [
                    'error'   => 'Invalid URL format',
                    'url'     => $url,
                    'success' => false,
                ];
            }

            try {
                // Fetch web content
                $webContent = $this->webFetcherService->fetch($url);

                // Call Site Fetcher AI (Level 2) to analyze content
                $extractedInfo = $this->analyzeWebContent($webContent, $enrichmentType);

                // Log successful tool call
                $this->aiLogger->logToolCall('', $functionName, $arguments, true);

                return [
                    'success'        => true,
                    'url'            => $url,
                    'reason'         => $reason,
                    'content_preview' => substr($webContent, 0, 500) . '...',
                    'extracted_info' => $extractedInfo,
                ];

            } catch (\Exception $e) {
                $this->logger->error('AI Enrichment: Tool call failed', [
                    'url'   => $url,
                    'error' => $e->getMessage(),
                ]);

                // Log failed tool call
                $this->aiLogger->logToolCall('', $functionName, $arguments, false, $e->getMessage());

                return [
                    'success' => false,
                    'url'     => $url,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return [
            'error'   => 'Unknown tool: ' . $functionName,
            'success' => false,
        ];
    }

    /**
     * Site Fetcher AI (Level 2) - Analyze web content to extract information
     *
     * @param string $webContent
     * @param string $enrichmentType
     * @return string
     */
    private function analyzeWebContent(string $webContent, string $enrichmentType): string
    {
        $this->logger->debug('AI Enrichment: Site Fetcher analyzing content', [
            'enrichment_type' => $enrichmentType,
            'content_length'  => strlen($webContent),
        ]);

        $typeConfig = $this->typeRegistry->getType($enrichmentType);
        $typeLabel = $typeConfig['label'] ?? $enrichmentType;

        $messages = [
            [
                'role'    => 'system',
                'content' => $this->getFetcherSystemPrompt(),
            ],
            [
                'role'    => 'user',
                'content' => "Information type: $typeLabel\n\nAnalyze this web content and extract ONLY the requested information:\n\n$webContent",
            ],
        ];

        try {
            $response = $this->liteLLMService->getChatCompletion($messages, [
                'temperature' => 0.1,
                'max_tokens'  => 500,
                'model'       => $this->getModel(),
            ]);

            $extractedInfo = $response['choices'][0]['message']['content'] ?? 'NOT_FOUND';

            $this->logger->debug('AI Enrichment: Site Fetcher extracted info', [
                'extracted_info' => substr($extractedInfo, 0, 200),
            ]);

            return $extractedInfo;

        } catch (\Exception $e) {
            $this->logger->error('AI Enrichment: Site Fetcher failed', [
                'error' => $e->getMessage(),
            ]);

            return 'ERROR: ' . $e->getMessage();
        }
    }

    /**
     * Get web_search tool definition
     *
     * @return array
     */
    private function getWebSearchTool(): array
    {
        return [
            'type'     => 'function',
            'function' => [
                'name'        => 'web_search',
                'description' => 'Fetches content from a web URL to find company information. Use this to search on search engines or visit company websites.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'url' => [
                            'type'        => 'string',
                            'description' => 'The URL to fetch (search engine results, company website, etc.)',
                        ],
                        'reason' => [
                            'type'        => 'string',
                            'description' => 'Why you want to fetch this URL',
                        ],
                    ],
                    'required'   => ['url'],
                ],
            ],
        ];
    }

    /**
     * Get Assistant AI system prompt (Level 1)
     *
     * @return string
     */
    private function getAssistantSystemPrompt(): string
    {
        return $this->coreParametersHelper->get(
            'ai_enrichment_assistant_prompt',
            'You are an AI assistant helping to find company information on the web.'
        );
    }

    /**
     * Get Site Fetcher AI system prompt (Level 2)
     *
     * @return string
     */
    private function getFetcherSystemPrompt(): string
    {
        return $this->coreParametersHelper->get(
            'ai_enrichment_fetcher_prompt',
            'You are analyzing web content to extract specific company information.'
        );
    }

    /**
     * Enrichment method that returns multiple options instead of single result
     *
     * @param string $companyName
     * @param string $enrichmentType
     * @param int|null $companyId
     * @return array
     * @throws \Exception
     */
    public function enrichWithOptions(string $companyName, string $enrichmentType, ?int $companyId = null): array
    {
        $this->logger->info('AI Enrichment: Starting enrichment with options', [
            'company_name'     => $companyName,
            'enrichment_type'  => $enrichmentType,
            'company_id'       => $companyId,
        ]);

        // Validate enrichment type
        if (!$this->typeRegistry->hasType($enrichmentType)) {
            throw new \Exception("Invalid enrichment type: $enrichmentType");
        }

        $typeConfig = $this->typeRegistry->getType($enrichmentType);

        // Build user prompt with instruction to return multiple options
        $userPrompt = str_replace('{company_name}', $companyName, $typeConfig['prompt']);
        $userPrompt .= "\n\nIMPORTANT: Return 2-5 different possible options as a comma-separated list. For example: 'option1, option2, option3'";

        // Step 1: Build initial messages for Assistant AI (Level 1)
        $messages = [
            [
                'role'    => 'system',
                'content' => $this->getAssistantSystemPromptForOptions(),
            ],
            [
                'role'    => 'user',
                'content' => $userPrompt,
            ],
        ];

        // Step 2: Define web_search tool
        $tools = [$this->getWebSearchTool()];

        // Step 3: Call Assistant AI in a loop (max 7 iterations)
        $maxIterations = 7;
        $iteration     = 0;

        while ($iteration < $maxIterations) {
            $this->logger->debug('AI Enrichment: Iteration ' . ($iteration + 1), [
                'messages_count' => count($messages),
            ]);

            // Log AI request to dedicated log file
            $this->aiLogger->logRequest($companyName, $enrichmentType, $messages);

            // Call Assistant AI with tools
            $response = $this->liteLLMService->getChatCompletion($messages, [
                'tools'       => $tools,
                'tool_choice' => 'auto',
                'temperature' => 0.3,
                'model'       => $this->getModel(),
            ]);

            // Log AI response to dedicated log file
            $this->aiLogger->logResponse($companyName, $enrichmentType, $response, $iteration + 1);

            $assistantMessage = $response['choices'][0]['message'];

            // Add assistant's response to conversation
            $messages[] = $assistantMessage;

            // Check if assistant wants to call a tool
            if (isset($assistantMessage['tool_calls']) && !empty($assistantMessage['tool_calls'])) {
                $this->logger->debug('AI Enrichment: Assistant requested tool calls', [
                    'tool_calls_count' => count($assistantMessage['tool_calls']),
                ]);

                // Execute each tool call
                foreach ($assistantMessage['tool_calls'] as $toolCall) {
                    $toolResult = $this->executeToolCallForOptions($toolCall, $enrichmentType);

                    // Add tool result to conversation
                    $messages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'content'      => json_encode($toolResult),
                    ];
                }

                $iteration++;
                continue; // Loop back to get next AI response
            }

            // No more tool calls, we have the final answer
            $finalAnswer = $assistantMessage['content'] ?? 'No options found';

            $this->logger->info('AI Enrichment: Completed successfully with options', [
                'company_name'    => $companyName,
                'enrichment_type' => $enrichmentType,
                'iterations'      => $iteration,
                'result'          => substr($finalAnswer, 0, 200),
            ]);

            // Log final result to dedicated log file
            $this->aiLogger->logResult($companyName, $enrichmentType, $finalAnswer, $iteration);

            return [
                'success'    => true,
                'result'     => $finalAnswer,
                'iterations' => $iteration,
            ];
        }

        // Max iterations reached
        $this->logger->warning('AI Enrichment: Max iterations reached', [
            'company_name'    => $companyName,
            'enrichment_type' => $enrichmentType,
        ]);

        $error = 'Max iterations reached without finding options. Please try again.';
        $this->aiLogger->logError($companyName, $enrichmentType, $error, ['iterations' => $maxIterations]);

        throw new \Exception($error);
    }

    /**
     * Execute a tool call for options enrichment
     *
     * @param array $toolCall
     * @param string $enrichmentType
     * @return array
     */
    private function executeToolCallForOptions(array $toolCall, string $enrichmentType): array
    {
        $functionName = $toolCall['function']['name'];
        $arguments    = json_decode($toolCall['function']['arguments'], true);

        $this->logger->debug('AI Enrichment: Executing tool call for options', [
            'function' => $functionName,
            'arguments' => $arguments,
        ]);

        if ($functionName === 'web_search') {
            $url = $arguments['url'] ?? '';
            $reason = $arguments['reason'] ?? 'Not specified';

            if (empty($url)) {
                return [
                    'error'   => 'URL is required',
                    'success' => false,
                ];
            }

            // Validate URL
            if (!$this->webFetcherService->isValidUrl($url)) {
                return [
                    'error'   => 'Invalid URL format',
                    'url'     => $url,
                    'success' => false,
                ];
            }

            try {
                // Fetch web content
                $webContent = $this->webFetcherService->fetch($url);

                // Call Site Fetcher AI (Level 2) to analyze content and return multiple options
                $extractedInfo = $this->analyzeWebContentForOptions($webContent, $enrichmentType);

                // Log successful tool call
                $this->aiLogger->logToolCall('', $functionName, $arguments, true);

                return [
                    'success'        => true,
                    'url'            => $url,
                    'reason'         => $reason,
                    'content_preview' => substr($webContent, 0, 500) . '...',
                    'extracted_info' => $extractedInfo,
                ];

            } catch (\Exception $e) {
                $this->logger->error('AI Enrichment: Tool call failed for options', [
                    'url'   => $url,
                    'error' => $e->getMessage(),
                ]);

                // Log failed tool call
                $this->aiLogger->logToolCall('', $functionName, $arguments, false, $e->getMessage());

                return [
                    'success' => false,
                    'url'     => $url,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return [
            'error'   => 'Unknown tool: ' . $functionName,
            'success' => false,
        ];
    }

    /**
     * Site Fetcher AI (Level 2) - Analyze web content to extract multiple options
     *
     * @param string $webContent
     * @param string $enrichmentType
     * @return string
     */
    private function analyzeWebContentForOptions(string $webContent, string $enrichmentType): string
    {
        $this->logger->debug('AI Enrichment: Site Fetcher analyzing content for options', [
            'enrichment_type' => $enrichmentType,
            'content_length'  => strlen($webContent),
        ]);

        $typeConfig = $this->typeRegistry->getType($enrichmentType);
        $typeLabel = $typeConfig['label'] ?? $enrichmentType;

        $messages = [
            [
                'role'    => 'system',
                'content' => $this->getFetcherSystemPromptForOptions(),
            ],
            [
                'role'    => 'user',
                'content' => "Information type: $typeLabel\n\nAnalyze this web content and extract 2-5 possible options for the requested information. Return them as a comma-separated list:\n\n$webContent",
            ],
        ];

        try {
            $response = $this->liteLLMService->getChatCompletion($messages, [
                'temperature' => 0.1,
                'max_tokens'  => 500,
                'model'       => $this->getModel(),
            ]);

            $extractedInfo = $response['choices'][0]['message']['content'] ?? 'NOT_FOUND';

            $this->logger->debug('AI Enrichment: Site Fetcher extracted options', [
                'extracted_info' => substr($extractedInfo, 0, 200),
            ]);

            return $extractedInfo;

        } catch (\Exception $e) {
            $this->logger->error('AI Enrichment: Site Fetcher failed for options', [
                'error' => $e->getMessage(),
            ]);

            return 'ERROR: ' . $e->getMessage();
        }
    }

    /**
     * Get Assistant AI system prompt for options (Level 1)
     *
     * @return string
     */
    private function getAssistantSystemPromptForOptions(): string
    {
        $basePrompt = $this->getAssistantSystemPrompt();
        return $basePrompt . "\n\nIMPORTANT: Return 2-5 different possible options as a comma-separated list.";
    }

    /**
     * Get Site Fetcher AI system prompt for options (Level 2)
     *
     * @return string
     */
    private function getFetcherSystemPromptForOptions(): string
    {
        $basePrompt = $this->getFetcherSystemPrompt();
        return $basePrompt . "\n\nIMPORTANT: Extract and return 2-5 different possible options as a comma-separated list. If you find multiple possibilities, include them all (up to 5).";
    }

    /**
     * Get configured AI model
     *
     * @return string
     */
    private function getModel(): string
    {
        return $this->coreParametersHelper->get('ai_enrichment_model', 'gpt-4');
    }
}
