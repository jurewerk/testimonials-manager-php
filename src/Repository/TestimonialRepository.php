<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

class TestimonialRepository
{
    private Database $db;

    private ImageRepository $images;

    public function __construct(Database $db, ImageRepository $images)
    {
        $this->db = $db;
        $this->images = $images;
    }

    public function find(int $id): ?array
    {
        $row = $this->db->selectOne('SELECT * FROM testimonials WHERE id = ?', [$id]);

        return $row === null ? null : $this->present($row, $this->images->forTestimonials([$id]));
    }

    public function findRaw(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM testimonials WHERE id = ?', [$id]);
    }

    public function countForLanding(int $landingId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM testimonials WHERE landing_id = ?', [$landingId]);
    }

    /**
     * A page of testimonials for one landing, with their images eagerly loaded
     * in a single extra query (no N+1).
     */
    public function paginateForLanding(int $landingId, int $perPage, int $page, bool $activeOnly = false): array
    {
        $where = 'WHERE landing_id = ?';
        $params = [$landingId];

        if ($activeOnly) {
            $where .= ' AND is_active = 1';
        }

        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM testimonials $where", $params);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $offset = ($page - 1) * $perPage;

        $rows = $this->db->select(
            "SELECT * FROM testimonials $where ORDER BY sort_order ASC, id ASC LIMIT $perPage OFFSET $offset",
            $params
        );

        $images = $this->images->forTestimonials(array_map(static fn ($r) => (int) $r['id'], $rows));

        return [
            'data' => array_map(fn (array $row) => $this->present($row, $images), $rows),
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

    /** All rows for a landing, ordered. Used by copy and ordering operations. */
    public function allForLanding(int $landingId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM testimonials WHERE landing_id = ?'
            .($activeOnly ? ' AND is_active = 1' : '')
            .' ORDER BY sort_order ASC, id ASC';

        return $this->db->select($sql, [$landingId]);
    }

    public function idsForLanding(int $landingId): array
    {
        return array_map('intval', array_column(
            $this->db->select('SELECT id FROM testimonials WHERE landing_id = ? ORDER BY sort_order ASC, id ASC', [$landingId]),
            'id'
        ));
    }

    public function nextSortOrder(int $landingId): int
    {
        $max = $this->db->scalar('SELECT MAX(sort_order) FROM testimonials WHERE landing_id = ?', [$landingId]);

        return $max === null || $max === false ? 0 : ((int) $max) + 1;
    }

    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO testimonials (landing_id, author_name, comment, link, rating_mode, rating, gender, is_active, sort_order, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['landing_id'], $data['author_name'], $data['comment'], $data['link'],
                $data['rating_mode'], $data['rating'], $data['gender'],
                $data['is_active'] ? 1 : 0, $data['sort_order'], $data['created_by'], $data['created_by'],
            ]
        );
    }

    public function update(int $id, array $data, ?int $userId): void
    {
        $this->db->execute(
            'UPDATE testimonials
             SET author_name = ?, comment = ?, link = ?, rating_mode = ?, rating = ?, gender = ?, is_active = ?,
                 version = version + 1, updated_by = ?
             WHERE id = ?',
            [
                $data['author_name'], $data['comment'], $data['link'], $data['rating_mode'],
                $data['rating'], $data['gender'], $data['is_active'] ? 1 : 0, $userId, $id,
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM testimonials WHERE id = ?', [$id]);
    }

    public function setActive(array $ids, bool $active, ?int $userId): int
    {
        if ($ids === []) {
            return 0;
        }

        $in = Database::placeholders($ids);

        return $this->db->execute(
            "UPDATE testimonials SET is_active = ?, version = version + 1, updated_by = ? WHERE id IN ($in)",
            array_merge([$active ? 1 : 0, $userId], $ids)
        );
    }

    public function deleteMany(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $in = Database::placeholders($ids);

        return $this->db->execute("DELETE FROM testimonials WHERE id IN ($in)", $ids);
    }

    /**
     * Writes a complete, gapless ordering in one statement.
     */
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

        $this->db->execute("UPDATE testimonials SET sort_order = CASE id $cases END WHERE id IN ($in)", $params);
    }

    public function present(array $row, array $imagesByTestimonial = []): array
    {
        $id = (int) $row['id'];

        return [
            'id' => $id,
            'landing_id' => (int) $row['landing_id'],
            'author_name' => $row['author_name'],
            'comment' => $row['comment'],
            'link' => $row['link'],
            'rating_mode' => $row['rating_mode'],
            'rating' => $row['rating'] === null ? null : (int) $row['rating'],
            'gender' => $row['gender'],
            'is_active' => (bool) $row['is_active'],
            'sort_order' => (int) $row['sort_order'],
            'version' => (int) $row['version'],
            'updated_at' => $row['updated_at'],
            'images' => $imagesByTestimonial[$id] ?? [],
        ];
    }

    /**
     * Public shape: no authorship, no audit fields, and a random rating is
     * resolved to 4 or 5 on every response so the average stays realistic.
     */
    public function presentPublic(array $row, array $imagesByTestimonial = []): array
    {
        $id = (int) $row['id'];

        return [
            'id' => $id,
            'author_name' => $row['author_name'],
            'comment' => $row['comment'],
            'link' => $row['link'],
            'rating' => $row['rating_mode'] === 'random' ? random_int(4, 5) : (int) $row['rating'],
            'images' => array_map(
                static fn (array $i) => ['url' => $i['url'], 'thumbnail_url' => $i['thumbnail_url'], 'width' => $i['width'], 'height' => $i['height']],
                $imagesByTestimonial[$id] ?? []
            ),
        ];
    }
}
