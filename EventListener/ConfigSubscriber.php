<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAiEnrichmentBundle\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use MauticPlugin\MauticAiEnrichmentBundle\Form\Type\ConfigType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfigSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ConfigEvents::CONFIG_ON_GENERATE => ['onConfigGenerate', 0],
        ];
    }

    /**
     * Register configuration form in Mautic settings
     *
     * @param ConfigBuilderEvent $event
     * @return void
     */
    public function onConfigGenerate(ConfigBuilderEvent $event): void
    {
        $event->addForm([
            'bundle'     => 'MauticAiEnrichmentBundle',
            'formType'   => ConfigType::class,
            'formAlias'  => 'aienrichmentconfig',
            'formTheme'  => '@MauticAiEnrichment/FormTheme/Config/_config_aienrichmentconfig_widget.html.twig',
            'parameters' => $event->getParametersFromConfig('MauticAiEnrichmentBundle'),
        ]);
    }
}
