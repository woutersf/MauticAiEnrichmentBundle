<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAiEnrichmentBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AssetSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_ASSETS => ['injectAssets', 0],
        ];
    }

    /**
     * Inject JavaScript assets
     */
    public function injectAssets(CustomAssetsEvent $event): void
    {
        $event->addScript('plugins/MauticAiEnrichmentBundle/Assets/js/enrichment.js');
        $event->addScript('plugins/MauticAiEnrichmentBundle/Assets/js/inline-enrichment.js');
    }
}
