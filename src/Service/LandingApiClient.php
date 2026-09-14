<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\HttpException;

/**
 * Talks to the provider's GET /landings endpoint.
 *
 * The key travels only in the X-Api-Key header, never in the query string or a
 * log line.
 */
class LandingApiClient
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function fetchPage(int $limit, int $offset, array $filters = []): array
    {
        if (($this->config['url'] ?? '') === '' || ($this->config['key'] ?? '') === '') {
            throw new HttpException('The landings API endpoint and key are not configured. Set them in config/config.local.php.', 503);
        }

        $query = ['limit' => $limit, 'offset' => $offset];

        if (! empty($filters['sku'])) {
            $query['sku'] = $filters['sku'];
        }

        if (! empty($filters['country'])) {
            $query['country'] = $filters['country'];
        }

        $url = $this->config['url'].'?'.http_build_query($query);
        $body = $this->request($url);
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new HttpException('The landings provider returned a response that could not be read.', 502);
        }

        // Accept a bare list, {"data": [...]} or {"landings": [...]}.
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }

        if (isset($decoded['landings']) && is_array($decoded['landings'])) {
            return $decoded['landings'];
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        throw new HttpException('The landings provider returned an unexpected envelope.', 502);
    }

    private function request(string $url): string
    {
        $attempts = 0;

        do {
            $attempts++;

            if (function_exists('curl_init')) {
                $handle = curl_init($url);
                curl_setopt_array($handle, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['X-Api-Key: '.$this->config['key'], 'Accept: application/json'],
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT => $this->config['timeout'],
                ]);

                $body = curl_exec($handle);
                $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                $error = curl_error($handle);
                curl_close($handle);
            } else {
                $context = stream_context_create(['http' => [
                    'method' => 'GET',
                    'header' => "X-Api-Key: {$this->config['key']}\r\nAccept: application/json\r\n",
                    'timeout' => $this->config['timeout'],
                    'ignore_errors' => true,
                ]]);

                $body = @file_get_contents($url, false, $context);
                $status = 0;
                $error = $body === false ? 'request failed' : '';

                foreach ($http_response_header ?? [] as $header) {
                    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
                        $status = (int) $m[1];
                    }
                }
            }

            if ($body !== false && $status >= 200 && $status < 300) {
                return (string) $body;
            }

            // Retry transient failures only.
            $retryable = $status === 0 || $status === 429 || $status >= 500;
        } while ($retryable && $attempts < 3);

        // The provider's response body is never surfaced: it could echo the key.
        throw new HttpException(sprintf('The landings provider could not be reached (HTTP %d).', $status), 502);
    }
}
