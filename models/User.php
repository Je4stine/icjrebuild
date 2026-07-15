<?php
class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function create($data) {
        $sql = "INSERT INTO users (first_name, last_name, email, password) 
                VALUES (:first_name, :last_name, :email, :password)";
        
        $params = [
            ':first_name' => $data['firstname'],
            ':last_name' => $data['lastname'],
            ':email' => $data['email'],
            ':password' => password_hash($data['password'], PASSWORD_BCRYPT)
        ];
        
        // Execute insert and get the last inserted ID
        $this->db->execute($sql, $params);
        $lastInsertId = $this->db->lastInsertId();
        
        // Fetch the created user
        return $this->findById($lastInsertId);
    }

    public function createOAuthUser($data) {
        $sql = "INSERT INTO users (first_name, last_name, email, password)
                VALUES (:first_name, :last_name, :email, :password)";

        $params = [
            ':first_name' => $data['firstname'],
            ':last_name' => $data['lastname'],
            ':email' => $data['email'],
            ':password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT)
        ];

        $this->db->execute($sql, $params);
        $lastInsertId = $this->db->lastInsertId();
        return $this->findById($lastInsertId);
    }

    public function findOrCreateOAuthUser($data) {
        $user = $this->findByEmail($data['email']);
        if ($user) {
            return $user;
        }

        return $this->createOAuthUser($data);
    }

    public function toAuthResponse($user) {
        return [
            'id' => (int)$user['id'],
            'firstname' => $user['first_name'],
            'lastname' => $user['last_name'],
            'email' => $user['email']
        ];
    }
    
    public function findById($id) {
        $sql = "SELECT id, first_name, last_name, email, password FROM users WHERE id = :id";
        return $this->db->fetch($sql, [':id' => $id]);
    }
    
    public function findByEmail($email) {
        $sql = "SELECT id, first_name, last_name, email, password FROM users WHERE email = :email";
        return $this->db->fetch($sql, [':email' => $email]);
    }
    
    public function existsByEmail($email) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE email = :email";
        $result = $this->db->fetch($sql, [':email' => $email]);
        return $result['count'] > 0;
    }
    
    public function updatePassword($email, $newPassword) {
        $sql = "UPDATE users SET password = :password WHERE email = :email";
        $params = [
            ':password' => password_hash($newPassword, PASSWORD_BCRYPT),
            ':email' => $email
        ];
        
        return $this->db->execute($sql, $params);
    }

    public function resetPassword($userId, $newPassword) {
        $sql = "UPDATE users SET password = :password WHERE id = :id";
        return $this->db->execute($sql, [
            ':password' => password_hash($newPassword, PASSWORD_BCRYPT),
            ':id' => $userId
        ]);
    }

    public function updateProfile($userId, $data) {
        $sql = "UPDATE users
                SET first_name = :first_name,
                    last_name = :last_name
                WHERE id = :id";

        $this->db->execute($sql, [
            ':first_name' => $data['firstname'],
            ':last_name' => $data['lastname'],
            ':id' => $userId
        ]);

        return $this->findById($userId);
    }

    public function getPreferences($userId) {
        $sql = "SELECT user_id, notification_settings, privacy_settings, accessibility_settings, language
                FROM user_preferences
                WHERE user_id = :user_id
                LIMIT 1";

        $row = $this->db->fetch($sql, [':user_id' => $userId]);
        if (!$row) {
            return [
                'notificationSettings' => [
                    'emailNotifications' => true,
                    'postUpdates' => true,
                    'forumActivity' => true,
                    'systemAnnouncements' => true
                ],
                'privacySettings' => [
                    'profileVisibility' => 'public',
                    'showEmail' => false,
                    'showPhone' => false,
                    'allowDataCollection' => true
                ],
                'accessibilitySettings' => [
                    'fontSize' => 'medium',
                    'highContrast' => false,
                    'reducedMotion' => false,
                    'darkMode' => false
                ],
                'language' => 'english'
            ];
        }

        return [
            'notificationSettings' => json_decode($row['notification_settings'] ?? '{}', true) ?: [
                'emailNotifications' => true,
                'postUpdates' => true,
                'forumActivity' => true,
                'systemAnnouncements' => true
            ],
            'privacySettings' => json_decode($row['privacy_settings'] ?? '{}', true) ?: [
                'profileVisibility' => 'public',
                'showEmail' => false,
                'showPhone' => false,
                'allowDataCollection' => true
            ],
            'accessibilitySettings' => json_decode($row['accessibility_settings'] ?? '{}', true) ?: [
                'fontSize' => 'medium',
                'highContrast' => false,
                'reducedMotion' => false,
                'darkMode' => false
            ],
            'language' => $row['language'] ?? 'english'
        ];
    }

    public function upsertPreferences($userId, $data) {
        $existing = $this->db->fetch(
            "SELECT user_id FROM user_preferences WHERE user_id = :user_id",
            [':user_id' => $userId]
        );

        $notificationSettings = array_key_exists('notificationSettings', $data)
            ? json_encode($data['notificationSettings'])
            : null;
        $privacySettings = array_key_exists('privacySettings', $data)
            ? json_encode($data['privacySettings'])
            : null;
        $accessibilitySettings = array_key_exists('accessibilitySettings', $data)
            ? json_encode($data['accessibilitySettings'])
            : null;
        $language = $data['language'] ?? null;

        if ($existing) {
            $sql = "UPDATE user_preferences
                    SET notification_settings = COALESCE(:notification_settings, notification_settings),
                        privacy_settings = COALESCE(:privacy_settings, privacy_settings),
                        accessibility_settings = COALESCE(:accessibility_settings, accessibility_settings),
                        language = COALESCE(:language, language)
                    WHERE user_id = :user_id";

            $this->db->execute($sql, [
                ':notification_settings' => $notificationSettings,
                ':privacy_settings' => $privacySettings,
                ':accessibility_settings' => $accessibilitySettings,
                ':language' => $language,
                ':user_id' => $userId
            ]);
        } else {
            $sql = "INSERT INTO user_preferences (user_id, notification_settings, privacy_settings, accessibility_settings, language)
                    VALUES (:user_id, :notification_settings, :privacy_settings, :accessibility_settings, :language)";

            $this->db->execute($sql, [
                ':user_id' => $userId,
                ':notification_settings' => $notificationSettings ?? json_encode([
                    'emailNotifications' => true,
                    'postUpdates' => true,
                    'forumActivity' => true,
                    'systemAnnouncements' => true
                ]),
                ':privacy_settings' => $privacySettings ?? json_encode([
                    'profileVisibility' => 'public',
                    'showEmail' => false,
                    'showPhone' => false,
                    'allowDataCollection' => true
                ]),
                ':accessibility_settings' => $accessibilitySettings ?? json_encode([
                    'fontSize' => 'medium',
                    'highContrast' => false,
                    'reducedMotion' => false,
                    'darkMode' => false
                ]),
                ':language' => $language ?? 'english'
            ]);
        }

        return $this->getPreferences($userId);
    }

    public function deleteAccount($userId) {
        return $this->db->execute("DELETE FROM users WHERE id = :id", [':id' => $userId]);
    }
    
    public function delete($email) {
        $sql = "DELETE FROM users WHERE email = :email";
        return $this->db->execute($sql, [':email' => $email]);
    }
    
    public function verifyPassword($email, $password) {
        $user = $this->findByEmail($email);
        if (!$user) {
            return false;
        }
        
        return password_verify($password, $user['password']);
    }
    
    public function getAllPosts($userId) {
        $sql = "SELECT p.*, u.first_name, u.last_name 
                FROM posts p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.user_id = :user_id 
                ORDER BY p.created_at DESC";
        
        return $this->db->fetchAll($sql, [':user_id' => $userId]);
    }
}
