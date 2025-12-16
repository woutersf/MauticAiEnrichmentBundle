<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAiEnrichmentBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;

class ButtonSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private CorePermissions $security
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectEnrichmentButton', 0],
        ];
    }

    /**
     * Inject "Enrich with AI" button on company new/edit pages
     *
     * @param CustomButtonEvent $event
     * @return void
     */
    public function injectEnrichmentButton(CustomButtonEvent $event): void
    {
        // Only on company new/edit pages
        $route = $event->getRoute();
        if (!str_contains($route, 'mautic_company_action')) {
            return;
        }

        // Check if user has permission to edit companies
        if (!$this->security->isGranted(['lead:leads:editown', 'lead:leads:editother'], 'MATCH_ONE')) {
            return;
        }

        // Only add to page actions (toolbar)
        if (ButtonHelper::LOCATION_PAGE_ACTIONS !== $event->getLocation()) {
            return;
        }

        // Get company ID from route parameters (might be null for new companies)
        $item      = $event->getItem();
        $companyId = $item ? $item->getId() : 'new';

        // If it's a new company being created, still show button but it will be limited
        if (!$companyId) {
            $companyId = 'new';
        }

        // Generate modal URL
        $modalUrl = $this->router->generate('mautic_company_enrichment_modal', [
            'companyId' => $companyId,
        ]);

        $event->addButton([
            'attr' => [
                'data-toggle' => 'ajaxmodal',
                'data-target' => '#MauticSharedModal',
                'data-header' => 'Enrich Company with AI',
                'href'        => $modalUrl,
                'class'       => 'btn btn-ghost btn-dnd',
            ],
            'btnText'   => 'Enrich with AI',
            'iconClass' => 'ri-sparkling-line',
            'priority'  => 200,
        ]);
    }
}
