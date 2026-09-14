<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

class ProductRepository
{
    private const SORTABLE = ['parent_sku', 'title', 'testimonials_count', 'landings_count'];

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Server-side search, sorting and pagination.
     *
     * Counts come from correlated aggregate subqueries rather than a query per
     * row, so the listing stays a single round trip regardless of page size.
     */
    public function paginate(string $search, string $sort, string $direction, int $perPage, int $page): array
    {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'parent_sku';
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        $where = '';
        $params = [];

        if ($search !== '') {
            $where = 'WHERE p.parent_sku LIKE ? OR m.title LIKE ? OR m.description LIKE ?';
            $like = '%'.$search.'%';
            $params = [$like, $like, $like];
        }

        $base = "FROM products p
            LEFT JOIN landings m ON m.product_id = p.id AND m.is_master = 1
            $where";

        $total = (int) $this->db->scalar("SELECT COUNT(*) $base", $params);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                p.id,
                p.parent_sku,
                m.title,
                m.description,
                m.product_image_url,
                m.last_synced_at,
                (SELECT COUNT(*) FROM landings l WHERE l.product_id = p.id AND l.is_master = 0) AS landings_count,
                (SELECT COUNT(*) FROM landings l WHERE l.product_id = p.id) AS all_landings_count,
                (SELECT COUNT(*) FROM testimonials t
                    JOIN landings l2 ON l2.id = t.landing_id
                    WHERE l2.product_id = p.id) AS testimonials_count
            $base
            ORDER BY $sort $direction, p.id ASC
            LIMIT $perPage OFFSET $offset";

        $rows = $this->db->select($sql, $params);

        return [
            'data' => array_map([$this, 'present'], $rows),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total === 0 ? null : $offset + 1,
                'to' => $total === 0 ? null : min($offset + $perPage, $total),
            ],
        ];
    }

    public function find(int $id): ?array
    {
        $row = $this->db->selectOne(
            'SELECT p.id, p.parent_sku, m.title, m.description, m.product_image_url, m.last_synced_at
             FROM products p
             LEFT JOIN landings m ON m.product_id = p.id AND m.is_master = 1
             WHERE p.id = ?',
            [$id]
        );

        return $row === null ? null : $this->present($row);
    }

    public function findOrCreateBySku(string $sku): int
    {
        $id = $this->db->scalar('SELECT id FROM products WHERE parent_sku = ?', [$sku]);

        if ($id !== false && $id !== null) {
            return (int) $id;
        }

        return $this->db->insert('INSERT INTO products (parent_sku) VALUES (?)', [$sku]);
    }

    private function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'parent_sku' => $row['parent_sku'],
            'title' => $row['title'] ?? $row['parent_sku'],
            'description' => $row['description'] ?? null,
            'product_image_url' => $row['product_image_url'] ?? null,
            'localized_landings_count' => isset($row['landings_count']) ? (int) $row['landings_count'] : null,
            'testimonials_count' => isset($row['testimonials_count']) ? (int) $row['testimonials_count'] : null,
            'last_synced_at' => $row['last_synced_at'] ?? null,
        ];
    }
}
