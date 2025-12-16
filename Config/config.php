<?php

declare(strict_types=1);

return [
    'name'        => 'AI Enrichment',
    'description' => 'AI-powered company data enrichment using web search',
    'version'     => '1.0.0',
    'author'      => 'Frederik Wouters',
    'icon'        => 'plugins/MauticAiEnrichmentBundle/Assets/mauticai.png',

    'routes' => [
        'main' => [
            'mautic_company_enrichment_modal' => [
                'path'       => '/companies/enrichment/modal/{companyId}',
                'controller' => 'MauticPlugin\MauticAiEnrichmentBundle\Controller\EnrichmentController::modalAction',
                'defaults'   => [
                    'companyId' => 'new',
                ],
            ],
            'mautic_company_enrichment_enrich' => [
                'path'       => '/companies/enrichment/enrich/{companyId}',
                'controller' => 'MauticPlugin\MauticAiEnrichmentBundle\Controller\EnrichmentController::enrichAction',
            ],
            'mautic_company_enrichment_options' => [
                'path'       => '/companies/enrichment/options/{companyId}',
                'controller' => 'MauticPlugin\MauticAiEnrichmentBundle\Controller\EnrichmentController::optionsAction',
            ],
            'mautic_company_enrichment_save' => [
                'path'       => '/companies/enrichment/save/{companyId}',
                'controller' => 'MauticPlugin\MauticAiEnrichmentBundle\Controller\EnrichmentController::saveAction',
            ],
        ],
    ],

    'services' => [
        'events' => [
            'mautic.ai_enrichment.button.subscriber' => [
                'class' => MauticPlugin\MauticAiEnrichmentBundle\EventListener\ButtonSubscriber::class,
                'arguments' => [
                    'router',
                    'mautic.security',
                ],
                'tags' => [
                    'kernel.event_subscriber',
                ],
            ],
            'mautic.ai_enrichment.config.subscriber' => [
                'class' => MauticPlugin\MauticAiEnrichmentBundle\EventListener\ConfigSubscriber::class,
                'tags' => [
                    'kernel.event_subscriber',
                ],
            ],
            'mautic.ai_enrichment.asset.subscriber' => [
                'class' => MauticPlugin\MauticAiEnrichmentBundle\EventListener\AssetSubscriber::class,
                'tags' => [
                    'kernel.event_subscriber',
                ],
            ],
        ],
        'forms' => [
            'mautic.ai_enrichment.form.type.config' => [
                'class' => MauticPlugin\MauticAiEnrichmentBundle\Form\Type\ConfigType::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                    'mautic.ai_connection.service.litellm',
                ],
                'tags' => [
                    'form.type',
                ],
            ],
        ],
        'other' => [
            'mautic.ai_enrichment.service.enrichment' => [
                'class' => MauticPlugin\MauticAiEnrichmentBundle\Service\EnrichmentService::class,
                'arguments' => [
                    'mautic.ai_connection.service.litellm',
                    'mautic.ai_enrichment.service.web_fetcher',
                    'mautic.ai_enrichment.service.type_registry',
                    'mautic.helper.core_parameters',
                    'monolog.logger.mautic',
                    'mautic.ai_log.service.logger',
                ],
            ],
            'mautic.ai_enrichment.service.web_fetcher' => [
                'class' => MauticPlugin\MauticAiEnrichmentBundle\Service\WebFetcherService::class,
                'arguments' => [
                    'mautic.http.client',
                    'monolog.logger.mautic',
                ],
            ],
            'mautic.ai_enrichment.service.type_registry' => [
                'class' => MauticPlugin\MauticAiEnrichmentBundle\Service\EnrichmentTypeRegistry::class,
            ],
        ],
        'integrations' => [
            'mautic.integration.aienrichment' => [
                'class' => MauticPlugin\MauticAiEnrichmentBundle\Integration\AiEnrichmentIntegration::class,
                'tags'  => [
                    'mautic.integration',
                    'mautic.basic_integration',
                ],
            ],
            'mautic.integration.aienrichment.configuration' => [
                'class' => MauticPlugin\MauticAiEnrichmentBundle\Integration\Support\ConfigSupport::class,
                'tags'  => [
                    'mautic.config_integration',
                ],
            ],
        ],
    ],

    'parameters' => [
        'ai_enrichment_model' => 'gpt-4',
        'ai_enrichment_assistant_prompt' => 'You are an AI assistant helping to find company information on the web.

STRATEGY when asked to find information:
1. Use the web_search tool to fetch relevant URLs (search engines, company websites, company contact urls)
2. Try multiple search approaches:
   - Search engines: DuckDuckGo, Google
   - Direct company website guess (e.g., https://companyname.com)
3. Analyze returned content for the requested information
4. If not found, try alternative URLs (you may use the urls from the google or duck duck go results) 
5. Return ONLY the extracted information in a clear format

If you can\'t find the information after trying multiple sources, return "UNKNOWN".
if found: only return the VALUE, no response or pleasantries. the value only.',

        'ai_enrichment_fetcher_prompt' => 'You are analyzing web content to extract specific company information.

You receive:
1. Web page content (HTML converted to text)
2. Information type (website, address, phone, email, employees)

Extract ONLY the requested value from the content.
- For website: return full URL with https://
- For address: return complete physical address like so: Country / Zip code / City / Adress 1 / Adress 2 
- For phone: return only the phone number
- For email: return contact email address
- For employees: return number only

If not found in content, return "NOT_FOUND
if found: only return the VALUE, no response or pleasantries. the valuel only.
".
',
    ],
];
