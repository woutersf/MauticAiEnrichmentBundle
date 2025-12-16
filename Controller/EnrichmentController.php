<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAiEnrichmentBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\MauticAiEnrichmentBundle\Service\EnrichmentService;
use MauticPlugin\MauticAiEnrichmentBundle\Service\EnrichmentTypeRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EnrichmentController extends CommonController
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'mautic.ai_enrichment.service.enrichment'    => EnrichmentService::class,
            'mautic.ai_enrichment.service.type_registry' => EnrichmentTypeRegistry::class,
        ]);
    }

    /**
     * Display enrichment modal
     *
     * @param Request $request
     * @param string $companyId
     * @return Response
     */
    public function modalAction(Request $request, string $companyId = 'new'): Response
    {
        // Check permissions
        if (!$this->security->isGranted(['lead:leads:editown', 'lead:leads:editother'], 'MATCH_ONE')) {
            return $this->accessDenied();
        }

        // Get company if editing existing
        $company = null;
        if ($companyId !== 'new') {
            $companyModel = $this->getModel('lead.company');
            $company      = $companyModel->getEntity((int) $companyId);

            if (!$company) {
                return $this->notFound('Company not found');
            }
        }

        // Get enrichment types
        $typeRegistry    = $this->get('mautic.ai_enrichment.service.type_registry');
        $enrichmentTypes = $typeRegistry->getAvailableTypes();

        return $this->delegateView([
            'viewParameters'  => [
                'company'          => $company,
                'companyId'        => $companyId,
                'enrichmentTypes'  => $enrichmentTypes,
            ],
            'contentTemplate' => '@MauticAiEnrichment/Enrichment/modal.html.twig',
            'passthroughVars' => [
                'route' => false,
            ],
        ]);
    }

    /**
     * Execute enrichment (AJAX endpoint)
     *
     * @param Request $request
     * @param string $companyId
     * @return JsonResponse
     */
    public function enrichAction(Request $request, string $companyId): JsonResponse
    {
        // Check permissions
        if (!$this->security->isGranted(['lead:leads:editown', 'lead:leads:editother'], 'MATCH_ONE')) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $enrichmentType = $request->request->get('type');
        $companyName    = $request->request->get('companyName');

        if (!$enrichmentType || !$companyName) {
            return $this->json([
                'error' => 'Missing required parameters: type and companyName',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $enrichmentService = $this->get('mautic.ai_enrichment.service.enrichment');
            $typeRegistry      = $this->get('mautic.ai_enrichment.service.type_registry');

            // Validate type
            if (!$typeRegistry->hasType($enrichmentType)) {
                return $this->json([
                    'error' => 'Invalid enrichment type: ' . $enrichmentType,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Execute enrichment
            $result = $enrichmentService->enrich(
                $companyName,
                $enrichmentType,
                $companyId !== 'new' ? (int) $companyId : null
            );

            // Get field name for this enrichment type
            $typeConfig = $typeRegistry->getType($enrichmentType);

            return $this->json([
                'success'    => true,
                'result'     => $result['result'],
                'field'      => $typeConfig['field'],
                'iterations' => $result['iterations'],
            ]);

        } catch (\Exception $e) {
            // Log error using factory
            if ($this->factory && $this->factory->getLogger()) {
                $this->factory->getLogger()->error('AI Enrichment: Enrichment failed', [
                    'company_name'    => $companyName,
                    'enrichment_type' => $enrichmentType,
                    'error'           => $e->getMessage(),
                    'trace'           => $e->getTraceAsString(),
                ]);
            }

            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get enrichment options (AJAX endpoint) - returns multiple options instead of single result
     *
     * @param Request $request
     * @param string $companyId
     * @return JsonResponse
     */
    public function optionsAction(Request $request, string $companyId): JsonResponse
    {
        // Check permissions
        if (!$this->security->isGranted(['lead:leads:editown', 'lead:leads:editother'], 'MATCH_ONE')) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $enrichmentType = $request->request->get('type');
        $companyName    = $request->request->get('companyName');

        if (!$enrichmentType || !$companyName) {
            return $this->json([
                'error' => 'Missing required parameters: type and companyName',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $enrichmentService = $this->get('mautic.ai_enrichment.service.enrichment');
            $typeRegistry      = $this->get('mautic.ai_enrichment.service.type_registry');

            // Validate type
            if (!$typeRegistry->hasType($enrichmentType)) {
                return $this->json([
                    'error' => 'Invalid enrichment type: ' . $enrichmentType,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Execute enrichment to get multiple options
            $result = $enrichmentService->enrichWithOptions(
                $companyName,
                $enrichmentType,
                $companyId !== 'new' ? (int) $companyId : null
            );

            return $this->json([
                'success'    => true,
                'options'    => $result['result'], // Comma-separated options
                'iterations' => $result['iterations'],
            ]);

        } catch (\Exception $e) {
            // Log error using factory
            if ($this->factory && $this->factory->getLogger()) {
                $this->factory->getLogger()->error('AI Enrichment: Options fetch failed', [
                    'company_name'    => $companyName,
                    'enrichment_type' => $enrichmentType,
                    'error'           => $e->getMessage(),
                    'trace'           => $e->getTraceAsString(),
                ]);
            }

            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Save enriched data to company (AJAX endpoint)
     *
     * @param Request $request
     * @param string $companyId
     * @return JsonResponse
     */
    public function saveAction(Request $request, string $companyId): JsonResponse
    {
        // Check permissions
        if (!$this->security->isGranted(['lead:leads:editown', 'lead:leads:editother'], 'MATCH_ONE')) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $field = $request->request->get('field');
        $value = $request->request->get('value');

        if (!$field || $value === null || $value === '') {
            return $this->json([
                'error' => 'Missing required parameters: field and value',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($companyId === 'new') {
            return $this->json([
                'error' => 'Cannot save enrichment to unsaved company. Please save the company first.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $companyModel = $this->getModel('lead.company');
            $company      = $companyModel->getEntity((int) $companyId);

            if (!$company) {
                return $this->json([
                    'error' => 'Company not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Update company field
            $companyModel->setFieldValues($company, [$field => $value], true);
            $companyModel->saveEntity($company);

            // Log success using factory
            if ($this->factory && $this->factory->getLogger()) {
                $this->factory->getLogger()->info('AI Enrichment: Field saved successfully', [
                    'company_id' => $companyId,
                    'field'      => $field,
                    'value'      => substr($value, 0, 200),
                ]);
            }

            return $this->json([
                'success' => true,
                'message' => 'Field updated successfully',
            ]);

        } catch (\Exception $e) {
            // Log error using factory
            if ($this->factory && $this->factory->getLogger()) {
                $this->factory->getLogger()->error('AI Enrichment: Save failed', [
                    'company_id' => $companyId,
                    'field'      => $field,
                    'error'      => $e->getMessage(),
                ]);
            }

            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
