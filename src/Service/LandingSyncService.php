<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\LandingRepository;
use App\Repository\ProductRepository;
use App\Support\Database;
use App\Support\HttpException;

/**
 * Imports landings from the provider.
 *
 * Every page is fetched and validated in full before the transaction opens, so
 * a failure on page three cannot leave a half-imported table behind. Records
 * are then upserted on the provider's stable id — never truncated and
 * reinserted — which is what keeps testimonials attached to their landing.
 */
class LandingSyncService
{
    private Database $db;

    private LandingApiClient $client;

    private ProductRepository $products;

    private LandingRepository $landings;

    private int $pageSize;

    public function __construct(Database $db, LandingApiClient $client, ProductRepository $products, LandingRepository $landings, int $pageSize)
    {
        $this->db = $db;
        $this->client = $client;
        $this->products = $products;
        $this->landings = $landings;
        $this->pageSize = $pageSize;
    }

    public function sync(array $filters = [], ?int $pageSize = null): array
    {
        $limit = $pageSize ?? $this->pageSize;
        $offset = 0;
        $staged = [];
        $seenExternalIds = [];
        $seenProductCountry = [];
        $received = 0;

        // ---- Phase 1: fetch and validate everything, touching nothing. ----
        while (true) {
            $page = $this->client->fetchPage($limit, $offset, $filters);

            if ($page === []) {
                break;
            }

            foreach ($page as $raw) {
                $received++;
                $row = $this->map($raw, $received);

                if (isset($seenExternalIds[$row['external_id']])) {
                    throw new HttpException(sprintf('The provider returned the identity "%s" more than once.', $row['external_id']), 422);
                }

                $pair = $row['parent_sku'].'|'.$row['country_code'];

                if (isset($seenProductCountry[$pair])) {
                    throw new HttpException(sprintf('The provider returned two landings for %s.', $pair), 422);
                }

                $seenExternalIds[$row['external_id']] = true;
                $seenProductCountry[$pair] = true;
                $staged[] = $row;
            }

            if (count($page) < $limit) {
                break;
            }

            $offset += $limit;
        }

        // ---- Phase 2: one transaction, upsert only. ----
        return $this->db->transaction(function () use ($staged, $received) {
            $summary = ['received' => $received, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0];
            $productIds = [];

            foreach ($staged as $row) {
                $sku = $row['parent_sku'];
                $productIds[$sku] ??= $this->products->findOrCreateBySku($sku);

                $result = $this->landings->upsert([
                    'external_id' => $row['external_id'],
                    'product_id' => $productIds[$sku],
                    'country_code' => $row['country_code'],
                    'is_master' => $row['is_master'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'landing_url' => $row['landing_url'],
                    'product_image_url' => $row['product_image_url'],
                ]);

                $summary[$result]++;
            }

            return $summary;
        });
    }

    /**
     * Maps one provider row, accepting the documented field aliases.
     */
    private function map(array $raw, int $index): array
    {
        $externalId = $raw['id'] ?? $raw['external_id'] ?? null;
        $sku = $raw['parent_sku'] ?? null;
        $country = $raw['country'] ?? $raw['country_code'] ?? null;
        $title = $raw['title'] ?? null;
        $url = $raw['url'] ?? $raw['landing_url'] ?? null;

        foreach (['id' => $externalId, 'parent_sku' => $sku, 'country' => $country, 'title' => $title, 'url' => $url] as $field => $value) {
            if ($value === null || $value === '') {
                throw new HttpException(sprintf('Landing #%d from the provider is missing "%s".', $index, $field), 422);
            }
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new HttpException(sprintf('Landing #%d from the provider has an invalid URL.', $index), 422);
        }

        $country = strtoupper((string) $country);
        $image = $raw['image'] ?? $raw['product_image_url'] ?? $raw['image_url'] ?? null;

        return [
            'external_id' => (string) $externalId,
            'parent_sku' => (string) $sku,
            'country_code' => $country,
            // EN identifies the English master; the provider's own flag wins when present.
            'is_master' => (bool) ($raw['is_master'] ?? ($country === 'EN')),
            'title' => mb_substr((string) $title, 0, 255),
            'description' => isset($raw['description']) ? (string) $raw['description'] : null,
            'landing_url' => (string) $url,
            'product_image_url' => $image === null ? null : (string) $image,
        ];
    }
}
