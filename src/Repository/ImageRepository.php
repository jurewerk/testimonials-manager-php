<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

class ImageRepository
{
    private Database $db;

    private string $baseUrl = '';

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Loads images for many testimonials at once, keyed by testimonial id.
     */
    public function forTestimonials(array $testimonialIds): array
    {
        if ($testimonialIds === []) {
            return [];
        }

        $in = Database::placeholders($testimonialIds);
        $rows = $this->db->select(
            "SELECT * FROM testimonial_images WHERE testimonial_id IN ($in) ORDER BY sort_order ASC, id ASC",
            $testimonialIds
        );

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row['testimonial_id']][] = $this->present($row);
        }

        return $out;
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM testimonial_images WHERE id = ?', [$id]);
    }

    public function forTestimonial(int $testimonialId): array
    {
        return $this->db->select(
            'SELECT * FROM testimonial_images WHERE testimonial_id = ? ORDER BY sort_order ASC, id ASC',
            [$testimonialId]
        );
    }

    public function nextSortOrder(int $testimonialId): int
    {
        $max = $this->db->scalar('SELECT MAX(sort_order) FROM testimonial_images WHERE testimonial_id = ?', [$testimonialId]);

        return $max === null || $max === false ? 0 : ((int) $max) + 1;
    }

    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO testimonial_images (testimonial_id, path, thumbnail_path, original_filename, mime_type, size, width, height, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['testimonial_id'], $data['path'], $data['thumbnail_path'], $data['original_filename'],
                $data['mime_type'], $data['size'], $data['width'], $data['height'], $data['sort_order'],
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM testimonial_images WHERE id = ?', [$id]);
    }

    public function applyOrder(array $orderedIds): void
    {
        if ($orderedIds === []) {
            return;
        }

        $cases = '';
        $params = [];

        foreach (array_values($orderedIds) as $position => $id) {
            $cases .= ' WHEN ? THEN ?';
            $params[] = $id;
            $params[] = $position;
        }

        $in = Database::placeholders($orderedIds);
        $params = array_merge($params, $orderedIds);

        $this->db->execute("UPDATE testimonial_images SET sort_order = CASE id $cases END WHERE id IN ($in)", $params);
    }

    public function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'url' => $this->baseUrl.'/api/images/'.rawurlencode($row['path']),
            'thumbnail_url' => $this->baseUrl.'/api/images/'.rawurlencode($row['thumbnail_path']),
            'original_filename' => $row['original_filename'],
            'width' => (int) $row['width'],
            'height' => (int) $row['height'],
            'sort_order' => (int) $row['sort_order'],
        ];
    }
}
