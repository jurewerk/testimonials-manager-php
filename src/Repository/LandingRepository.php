<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

class LandingRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?array
    {
        $row = $this->db->selectOne('SELECT * FROM landings WHERE id = ?', [$id]);

        return $row === null ? null : $this->present($row);
    }

    public function findByExternalId(string $externalId): ?array
    {
        $row = $this->db->selectOne('SELECT * FROM landings WHERE external_id = ?', [$externalId]);

        return $row === null ? null : $this->present($row);
    }

    public function masterFor(int $productId): ?array
    {
        $row = $this->db->selectOne('SELECT * FROM landings WHERE product_id = ? AND is_master = 1', [$productId]);

        return $row === null ? null : $this->present($row);
    }

    /**
     * Every landing of a product with its own and effective testimonial counts.
     *
     * The counts are produced by one grouped query, so the country overview
     * never needs a query per country.
     */
    public function forProductWithCounts(int $productId): array
    {
        $rows = $this->db->select(
            'SELECT l.*,
                    COALESCE(c.total, 0)  AS own_count,
                    COALESCE(c.active, 0) AS own_active_count
             FROM landings l
             LEFT JOIN (
                 SELECT landing_id,
                        COUNT(*) AS total,
                        SUM(is_active = 1) AS active
                 FROM testimonials
                 GROUP BY landing_id
             ) c ON c.landing_id = l.id
             WHERE l.product_id = ?
             ORDER BY l.is_master DESC, l.country_code ASC',
            [$productId]
        );

        $master = null;

        foreach ($rows as $row) {
            if ((int) $row['is_master'] === 1) {
                $master = $row;
                break;
            }
        }

        $masterActive = $master === null ? 0 : (int) $master['own_active_count'];

        return array_map(function (array $row) use ($masterActive) {
            $landing = $this->present($row);
            $own = (int) $row['own_count'];
            $ownActive = (int) $row['own_active_count'];

            // Inheritance depends on the existence of ANY local row, including
            // inactive ones: a country that has deactivated everything shows an
            // empty list rather than falling back to English.
            $inherits = $own === 0 && (int) $row['is_master'] === 0;

            $landing['own_count'] = $own;
            $landing['own_active_count'] = $ownActive;
            $landing['inherits'] = $inherits;
            $landing['effective_count'] = $inherits ? $masterActive : $own;
            $landing['effective_active_count'] = $inherits ? $masterActive : $ownActive;

            return $landing;
        }, $rows);
    }

    public function bumpVersion(int $id): int
    {
        $this->db->execute('UPDATE landings SET version = version + 1 WHERE id = ?', [$id]);

        return (int) $this->db->scalar('SELECT version FROM landings WHERE id = ?', [$id]);
    }

    public function lockForUpdate(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM landings WHERE id = ? FOR UPDATE', [$id]);
    }

    /**
     * Upsert on the provider's stable external id.
     *
     * This is deliberately never a TRUNCATE + INSERT: the local id must survive
     * a re-import so testimonials keep pointing at their landing.
     *
     * @return string 'created'|'updated'|'unchanged'
     */
    public function upsert(array $landing): string
    {
        $existing = $this->db->selectOne('SELECT * FROM landings WHERE external_id = ?', [$landing['external_id']]);

        if ($existing === null) {
            $this->db->insert(
                'INSERT INTO landings (external_id, product_id, country_code, is_master, title, description, landing_url, product_image_url, last_synced_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $landing['external_id'], $landing['product_id'], $landing['country_code'],
                    $landing['is_master'] ? 1 : 0, $landing['title'], $landing['description'],
                    $landing['landing_url'], $landing['product_image_url'],
                ]
            );

            return 'created';
        }

        $changed = ((int) $existing['product_id'] !== (int) $landing['product_id'])
            || $existing['country_code'] !== $landing['country_code']
            || (int) $existing['is_master'] !== (int) ($landing['is_master'] ? 1 : 0)
            || $existing['title'] !== $landing['title']
            || $existing['description'] !== $landing['description']
            || $existing['landing_url'] !== $landing['landing_url']
            || $existing['product_image_url'] !== $landing['product_image_url'];

        if ($changed) {
            $this->db->execute(
                'UPDATE landings
                 SET product_id = ?, country_code = ?, is_master = ?, title = ?, description = ?,
                     landing_url = ?, product_image_url = ?, last_synced_at = NOW()
                 WHERE id = ?',
                [
                    $landing['product_id'], $landing['country_code'], $landing['is_master'] ? 1 : 0,
                    $landing['title'], $landing['description'], $landing['landing_url'],
                    $landing['product_image_url'], $existing['id'],
                ]
            );

            return 'updated';
        }

        // Even an unchanged record records that we heard from the provider.
        $this->db->execute('UPDATE landings SET last_synced_at = NOW() WHERE id = ?', [$existing['id']]);

        return 'unchanged';
    }

    private function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'external_id' => $row['external_id'],
            'product_id' => (int) $row['product_id'],
            'country_code' => $row['country_code'],
            'is_master' => (bool) $row['is_master'],
            'title' => $row['title'],
            'description' => $row['description'],
            'landing_url' => $row['landing_url'],
            'product_image_url' => $row['product_image_url'],
            'last_synced_at' => $row['last_synced_at'],
            'version' => (int) $row['version'],
        ];
    }
}
