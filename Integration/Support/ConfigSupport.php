<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAiEnrichmentBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\MauticAiEnrichmentBundle\Integration\AiEnrichmentIntegration;

class ConfigSupport extends AiEnrichmentIntegration implements ConfigFormInterface
{
    use DefaultConfigFormTrait;

    public function getAuthenticationType(): string
    {
        return 'none';
    }
}
