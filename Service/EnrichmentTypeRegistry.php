<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAiEnrichmentBundle\Service;

class EnrichmentTypeRegistry
{
    /**
     * Get all available enrichment types with their configuration
     *
     * @return array
     */
    public function getAvailableTypes(): array
    {
        return [
            'website' => [
                'label' => 'Find Website',
                'icon'  => 'ri-global-line',
                'field' => 'companywebsite',
                'prompt' => 'Find the official website URL for {company_name}. Look for the company\'s main website domain.',
            ],
            'address' => [
                'label' => 'Find Address',
                'icon'  => 'ri-map-pin-line',
                'field' => 'companyaddress1',
                'prompt' => 'Find the physical headquarters address for {company_name}. Include street, city, state/province, and postal code.',
            ],
            'phone' => [
                'label' => 'Find Phone',
                'icon'  => 'ri-phone-line',
                'field' => 'companyphone',
                'prompt' => 'Find the main contact phone number for {company_name}. Look for general inquiry or main office phone.',
            ],
            'email' => [
                'label' => 'Find Email',
                'icon'  => 'ri-mail-line',
                'field' => 'companyemail',
                'prompt' => 'Find the general contact email address for {company_name}. Look for info@, contact@, or general inquiry email.',
            ],
            'employees' => [
                'label' => 'Find Number of Employees',
                'icon'  => 'ri-team-line',
                'field' => 'companynumber_of_employees',
                'prompt' => 'Find the number of employees or company size for {company_name}. Return only the number.',
            ],
            'description' => [
                'label' => 'Generate Description',
                'icon'  => 'ri-file-text-line',
                'field' => 'companydescription',
                'prompt' => 'Generate a comprehensive company description for {company_name}. Include what they do, their main products or services, and their industry. Keep it concise (2-3 sentences).',
            ],
        ];
    }

    /**
     * Get a specific enrichment type configuration
     *
     * @param string $type
     * @return array|null
     */
    public function getType(string $type): ?array
    {
        $types = $this->getAvailableTypes();
        return $types[$type] ?? null;
    }

    /**
     * Check if an enrichment type exists
     *
     * @param string $type
     * @return bool
     */
    public function hasType(string $type): bool
    {
        return isset($this->getAvailableTypes()[$type]);
    }
}
