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
