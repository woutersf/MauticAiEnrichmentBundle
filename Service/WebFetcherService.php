<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAiEnrichmentBundle\Service;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class WebFetcherService
{
    public function __construct(
        private Client $httpClient,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Fetch content from a URL and convert to text
     *
     * @param string $url
     * @param int $timeout
     * @return string
     * @throws \Exception
     */
    public function fetch(string $url, int $timeout = 15): string
    {
        try {
            $this->logger->info('AI Enrichment: Fetching URL', ['url' => $url]);

            $response = $this->httpClient->request('GET', $url, [
                'timeout' => $timeout,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; MauticBot/1.0; +https://mautic.org)',
                    'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ],
                'verify'         => false, // Consider security implications in production
                'allow_redirects' => true,
                'http_errors'    => false, // Don't throw on 4xx/5xx
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                throw new \Exception("HTTP error $statusCode for URL: $url");
            }

            $html = $response->getBody()->getContents();

            // Convert HTML to plain text
            $text = $this->htmlToText($html);

            $this->logger->info('AI Enrichment: Successfully fetched URL', [
                'url'         => $url,
                'status_code' => $statusCode,
                'text_length' => strlen($text),
            ]);

            return $text;

        } catch (\Exception $e) {
            $this->logger->error('AI Enrichment: Failed to fetch URL', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception("Failed to fetch $url: " . $e->getMessage());
        }
    }

    /**
     * Convert HTML to plain text for AI analysis
     *
     * @param string $html
     * @return string
     */
    private function htmlToText(string $html): string
    {
        // Remove script and style tags with their content
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // Remove HTML comments
        $html = preg_replace('/<!--(.|\s)*?-->/', '', $html);

        // Convert common HTML entities
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Add line breaks before block elements
        $html = preg_replace('/<(div|p|br|h[1-6]|li|tr)/i', "\n<$1", $html);

        // Strip all remaining HTML tags
        $text = strip_tags($html);

        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n", $text);
        $text = trim($text);

        // Limit length to avoid token limits (10,000 chars)
        if (strlen($text) > 10000) {
            $text = substr($text, 0, 10000) . '...';
        }

        return $text;
    }

    /**
     * Validate URL format
     *
     * @param string $url
     * @return bool
     */
    public function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
