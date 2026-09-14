<?php

declare(strict_types=1);

namespace App\Repository;

use App\Support\Database;

class ActivityRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function record(?int $userId, string $action, string $entityType, int $entityId, ?array $old, ?array $new): void
    {
        $this->db->insert(
            'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, old_values, new_values) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $userId, $action, $entityType, $entityId,
                $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
                $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    /** History stays readable after the record itself is deleted. */
    public function forEntity(string $entityType, int $entityId): array
    {
        $rows = $this->db->select(
            'SELECT a.*, u.name AS user_name
             FROM activity_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.entity_type = ? AND a.entity_id = ?
             ORDER BY a.id DESC',
            [$entityType, $entityId]
        );

        return array_map(static fn (array $row) => [
            'id' => (int) $row['id'],
            'action' => $row['action'],
            'actor' => $row['user_name'],
            'old_values' => $row['old_values'] === null ? null : json_decode($row['old_values'], true),
            'new_values' => $row['new_values'] === null ? null : json_decode($row['new_values'], true),
            'created_at' => $row['created_at'],
        ], $rows);
    }
}
