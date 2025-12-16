<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAiEnrichmentBundle\Form\Type;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\MauticAIconnectionBundle\Service\LiteLLMService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class ConfigType extends AbstractType
{
    public function __construct(
        private CoreParametersHelper $coreParametersHelper,
        private LiteLLMService $liteLLMService
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Fetch available models from LiteLLM
        $modelChoices = $this->getModelChoices();

        // AI Model Selection
        $builder->add(
            'ai_enrichment_model',
            ChoiceType::class,
            [
                'choices'     => $modelChoices,
                'label'       => 'AI Model for Enrichment',
                'label_attr'  => [
                    'class' => 'control-label',
                ],
                'attr'        => [
                    'class'   => 'form-control',
                    'tooltip' => 'Select the AI model to use for company enrichment. GPT-4 is recommended for best results.',
                ],
                'required'    => false,
                'placeholder' => 'Select an AI model',
            ]
        );

        // Assistant AI Prompt (Level 1)
        $builder->add(
            'ai_enrichment_assistant_prompt',
            TextareaType::class,
            [
                'label'      => 'Assistant AI System Prompt (Level 1)',
                'label_attr' => [
                    'class' => 'control-label',
                ],
                'attr'       => [
                    'class'   => 'form-control',
                    'rows'    => 8,
                    'tooltip' => 'System prompt for the main AI that decides which URLs to search. This AI has access to the web_search tool.',
                ],
                'required'   => false,
                'data'       => $options['data']['ai_enrichment_assistant_prompt'] ?? $this->getDefaultAssistantPrompt(),
            ]
        );

        // Site Fetcher AI Prompt (Level 2)
        $builder->add(
            'ai_enrichment_fetcher_prompt',
            TextareaType::class,
            [
                'label'      => 'Site Fetcher AI System Prompt (Level 2)',
                'label_attr' => [
                    'class' => 'control-label',
                ],
                'attr'       => [
                    'class'   => 'form-control',
                    'rows'    => 8,
                    'tooltip' => 'System prompt for the AI that analyzes web content and extracts specific information.',
                ],
                'required'   => false,
                'data'       => $options['data']['ai_enrichment_fetcher_prompt'] ?? $this->getDefaultFetcherPrompt(),
            ]
        );
    }

    /**
     * Get available AI models from LiteLLM
     *
     * @return array
     */
    private function getModelChoices(): array
    {
        try {
            $models = $this->liteLLMService->getAvailableModels();

            // Convert models array to choices format (label => value)
            $choices = [];
            foreach ($models as $label => $value) {
                $choices[$label] = $value;
            }

            return $choices;

        } catch (\Exception $e) {
            // Fallback to default models if LiteLLM unavailable
            return [
                'GPT-4'              => 'gpt-4',
                'GPT-4 Turbo'        => 'gpt-4-turbo',
                'GPT-3.5 Turbo'      => 'gpt-3.5-turbo',
                'Claude 3 Opus'      => 'claude-3-opus',
                'Claude 3 Sonnet'    => 'claude-3-sonnet',
                'Claude 3 Haiku'     => 'claude-3-haiku',
                'Llama 2 70B'        => 'llama-2-70b',
            ];
        }
    }

    /**
     * Default Assistant AI prompt (Level 1)
     *
     * @return string
     */
    private function getDefaultAssistantPrompt(): string
    {
        return <<<'PROMPT'
You are an AI assistant helping to find company information on the web.

STRATEGY when asked to find information:
1. Use the web_search tool to fetch relevant URLs (search engines, company websites)
2. Try multiple search approaches:
   - Search engines: DuckDuckGo, Google
   - Direct company website guess (e.g., https://companyname.com)
3. Analyze returned content for the requested information
4. If not found, try alternative URLs
5. Return ONLY the extracted information in a clear format

If you can't find the information after trying multiple sources, return "UNKNOWN".
PROMPT;
    }

    /**
     * Default Site Fetcher AI prompt (Level 2)
     *
     * @return string
     */
    private function getDefaultFetcherPrompt(): string
    {
        return <<<'PROMPT'
You are analyzing web content to extract specific company information.

You receive:
1. Web page content (HTML converted to text)
2. Information type (website, address, phone, email, employees)

Extract ONLY the requested value from the content.
- For website: return full URL with https://
- For address: return complete physical address
- For phone: return phone number
- For email: return contact email address
- For employees: return number only

If not found in content, return "NOT_FOUND".
PROMPT;
    }

    public function getBlockPrefix(): string
    {
        return 'aienrichmentconfig';
    }
}
