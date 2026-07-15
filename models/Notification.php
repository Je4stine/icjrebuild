<?php
class Notification {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($data) {
        if (empty($data['userId']) || empty($data['title']) || empty($data['message'])) {
            return null;
        }

        if (!empty($data['actorId']) && (int)$data['actorId'] === (int)$data['userId']) {
            return null;
        }

        $sql = "INSERT INTO notifications
                    (id, user_id, actor_id, type, title, message, related_type, related_id)
                VALUES
                    (UUID(), :user_id, :actor_id, :type, :title, :message, :related_type, :related_id)";

        $params = [
            ':user_id' => (int)$data['userId'],
            ':actor_id' => isset($data['actorId']) ? (int)$data['actorId'] : null,
            ':type' => $data['type'] ?? 'system',
            ':title' => $data['title'],
            ':message' => $data['message'],
            ':related_type' => $data['relatedType'] ?? null,
            ':related_id' => isset($data['relatedId']) ? (string)$data['relatedId'] : null
        ];

        $this->db->execute($sql, $params);

        $notification = $this->db->fetch(
            "SELECT n.*, a.first_name AS actor_first_name, a.last_name AS actor_last_name
             FROM notifications n
             LEFT JOIN users a ON n.actor_id = a.id
             WHERE n.user_id = :user_id
             ORDER BY n.created_at DESC
             LIMIT 1",
            [':user_id' => (int)$data['userId']]
        );

        return $notification ? $this->toResponse($notification) : null;
    }

    public function getForUser($userId, $limit = 50, $offset = 0) {
        $sql = "SELECT n.*, a.first_name AS actor_first_name, a.last_name AS actor_last_name
                FROM notifications n
                LEFT JOIN users a ON n.actor_id = a.id
                WHERE n.user_id = :user_id AND n.deleted_at IS NULL
                ORDER BY n.created_at DESC
                LIMIT :limit OFFSET :offset";

        return array_map([$this, 'toResponse'], $this->db->fetchAll($sql, [
            ':user_id' => (int)$userId,
            ':limit' => (int)$limit,
            ':offset' => (int)$offset
        ]));
    }

    public function countForUser($userId) {
        $result = $this->db->fetch(
            "SELECT COUNT(*) AS count FROM notifications WHERE user_id = :user_id AND deleted_at IS NULL",
            [':user_id' => (int)$userId]
        );

        return (int)($result['count'] ?? 0);
    }

    public function getUnreadCount($userId) {
        $result = $this->db->fetch(
            "SELECT COUNT(*) AS count
             FROM notifications
             WHERE user_id = :user_id AND is_read = 0 AND deleted_at IS NULL",
            [':user_id' => (int)$userId]
        );

        return (int)($result['count'] ?? 0);
    }

    public function markAsRead($userId, $ids) {
        $ids = $this->normalizeIds($ids);
        if (empty($ids)) {
            return 0;
        }

        $placeholders = $this->placeholders($ids, 'id');
        $params = [':user_id' => (int)$userId];
        foreach ($ids as $index => $id) {
            $params[':id' . $index] = $id;
        }

        return $this->db->execute(
            "UPDATE notifications
             SET is_read = 1, read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
             WHERE user_id = :user_id AND id IN ($placeholders)",
            $params
        );
    }

    public function markAllAsRead($userId) {
        return $this->db->execute(
            "UPDATE notifications
             SET is_read = 1, read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
             WHERE user_id = :user_id AND is_read = 0 AND deleted_at IS NULL",
            [':user_id' => (int)$userId]
        );
    }

    public function deleteForUser($userId, $ids = []) {
        $ids = $this->normalizeIds($ids);

        if (empty($ids)) {
            return $this->db->execute(
                "UPDATE notifications SET deleted_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND deleted_at IS NULL",
                [':user_id' => (int)$userId]
            );
        }

        $placeholders = $this->placeholders($ids, 'id');
        $params = [':user_id' => (int)$userId];
        foreach ($ids as $index => $id) {
            $params[':id' . $index] = $id;
        }

        return $this->db->execute(
            "UPDATE notifications
             SET deleted_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id AND id IN ($placeholders)",
            $params
        );
    }

    private function normalizeIds($ids) {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        return array_values(array_filter(array_map('strval', $ids), function ($id) {
            return $id !== '';
        }));
    }

    private function placeholders($values, $prefix) {
        return implode(',', array_map(function ($index) use ($prefix) {
            return ':' . $prefix . $index;
        }, array_keys($values)));
    }

    private function toResponse($notification) {
        $actorName = trim(($notification['actor_first_name'] ?? '') . ' ' . ($notification['actor_last_name'] ?? ''));
        $relatedType = $notification['related_type'] ?? null;
        $relatedId = $notification['related_id'] ?? null;

        return [
            'id' => $notification['id'],
            'type' => $notification['type'] ?? 'system',
            'title' => $notification['title'],
            'message' => $notification['message'],
            'isRead' => (bool)($notification['is_read'] ?? false),
            'createdAt' => $notification['created_at'] ?? null,
            'readAt' => $notification['read_at'] ?? null,
            'actor' => $notification['actor_id'] ? [
                'id' => (int)$notification['actor_id'],
                'name' => $actorName
            ] : null,
            'relatedEntity' => ($relatedType && $relatedId) ? [
                'type' => $relatedType,
                'id' => $relatedId
            ] : null
        ];
    }
}
