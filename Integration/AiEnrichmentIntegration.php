<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAiEnrichmentBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;

class AiEnrichmentIntegration extends BasicIntegration implements BasicInterface
{
    use ConfigurationTrait;

    public const NAME = 'AiEnrichment';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDisplayName(): string
    {
        return 'AI Enrichment';
    }

    public function getIcon(): string
    {
        return 'plugins/MauticAiEnrichmentBundle/Assets/mauticai.png';
    }
}
