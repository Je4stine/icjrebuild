<?php
class Post {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function create($data, $mediaFile = null, $documentFile = null) {
        $this->db->beginTransaction();
        
        try {
            $sql = "INSERT INTO posts (id, post_title, post_content, user_id, media, document) 
                    VALUES (UUID(), :post_title, :post_content, :user_id, :media, :document)";
            
            $mediaData = null;
            $documentData = null;
            
            if ($mediaFile && $mediaFile['error'] === UPLOAD_ERR_OK) {
                $mediaData = file_get_contents($mediaFile['tmp_name']);
            }
            
            if ($documentFile && $documentFile['error'] === UPLOAD_ERR_OK) {
                $documentData = file_get_contents($documentFile['tmp_name']);
            }
            
            $params = [
                ':post_title' => $data['postTitle'],
                ':post_content' => $data['postContent'],
                ':user_id' => $data['userId'],
                ':media' => $mediaData,
                ':document' => $documentData
            ];
            
            $this->db->execute($sql, $params);
            
            // Get the created post (MySQL doesn't support RETURNING with UUID, so we need to find it)
            $getPostSql = "SELECT id, post_title, post_content, created_at FROM posts 
                          WHERE user_id = :user_id AND post_title = :post_title 
                          ORDER BY created_at DESC LIMIT 1";
            $result = $this->db->fetch($getPostSql, [
                ':user_id' => $data['userId'],
                ':post_title' => $data['postTitle']
            ]);
            
            $this->db->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function getAllPosts($page = 0, $pageSize = 10) {
        $offset = $page * $pageSize;
        
        $sql = "SELECT p.id, p.post_title, p.post_content, p.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       CASE WHEN p.media IS NOT NULL THEN true ELSE false END as has_media,
                       CASE WHEN p.document IS NOT NULL THEN true ELSE false END as has_document,
                       (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count
                FROM posts p 
                JOIN users u ON p.user_id = u.id 
                ORDER BY p.created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $params = [
            ':limit' => $pageSize,
            ':offset' => $offset
        ];
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getTotalCount() {
        $sql = "SELECT COUNT(*) as total FROM posts";
        $result = $this->db->fetch($sql);
        return $result['total'];
    }
    
    public function getById($id) {
        $sql = "SELECT p.id, p.post_title, p.post_content, p.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       CASE WHEN p.media IS NOT NULL THEN true ELSE false END as has_media,
                       CASE WHEN p.document IS NOT NULL THEN true ELSE false END as has_document,
                       (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count
                FROM posts p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.id = :id";
        
        return $this->db->fetch($sql, [':id' => $id]);
    }
    
    public function getImageById($id) {
        $sql = "SELECT media FROM posts WHERE id = :id AND media IS NOT NULL";
        $result = $this->db->fetch($sql, [':id' => $id]);
        return $result ? $result['media'] : null;
    }
    
    public function getPdfById($id) {
        $sql = "SELECT document FROM posts WHERE id = :id AND document IS NOT NULL";
        $result = $this->db->fetch($sql, [':id' => $id]);
        return $result ? $result['document'] : null;
    }
    
    public function delete($id) {
        $sql = "DELETE FROM posts WHERE id = :id";
        return $this->db->execute($sql, [':id' => $id]);
    }
    
    public function getPostsByUserId($userId) {
        $sql = "SELECT p.id, p.post_title, p.post_content, p.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       CASE WHEN p.media IS NOT NULL THEN true ELSE false END as has_media,
                       CASE WHEN p.document IS NOT NULL THEN true ELSE false END as has_document,
                       (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count
                FROM posts p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.user_id = :user_id 
                ORDER BY p.created_at DESC";
        
        return $this->db->fetchAll($sql, [':user_id' => $userId]);
    }
    
    public function checkUserOwnership($postId, $userId) {
        $sql = "SELECT COUNT(*) as count FROM posts WHERE id = :id AND user_id = :user_id";
        $result = $this->db->fetch($sql, [':id' => $postId, ':user_id' => $userId]);
        return $result['count'] > 0;
    }
}
