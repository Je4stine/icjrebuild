<?php
class UsersController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function search() {
        $query = trim($_GET['query'] ?? $_GET['q'] ?? '');
        $exclude = $_GET['exclude'] ?? null;
        $limit = min(max((int)($_GET['limit'] ?? 10), 1), 50);

        if ($query === '') {
            ResponseHelper::success(['users' => [], 'content' => []]);
        }

        $params = [
            ':query' => '%' . $query . '%',
            ':limit' => $limit
        ];

        $sql = "SELECT id, first_name, last_name, email
                FROM users
                WHERE (first_name LIKE :query OR last_name LIKE :query OR email LIKE :query)";

        if ($exclude !== null && $exclude !== '') {
            $sql .= " AND id != :exclude";
            $params[':exclude'] = (int)$exclude;
        }

        $sql .= " ORDER BY first_name ASC, last_name ASC LIMIT :limit";

        $users = array_map(function ($user) {
            $name = trim($user['first_name'] . ' ' . $user['last_name']);
            return [
                'id' => (int)$user['id'],
                'firstname' => $user['first_name'],
                'lastname' => $user['last_name'],
                'firstName' => $user['first_name'],
                'lastName' => $user['last_name'],
                'name' => $name,
                'email' => $user['email'],
                'avatar' => null,
                'isOnline' => false
            ];
        }, $this->db->fetchAll($sql, $params));

        ResponseHelper::success([
            'users' => $users,
            'content' => $users
        ]);
    }
}
