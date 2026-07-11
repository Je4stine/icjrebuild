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
        
        return array_map([$this, 'toFrontendResponse'], $this->db->fetchAll($sql, $params));
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
                WHERE p.id = :id OR p.post_title = :title
                LIMIT 1";
        
        $post = $this->db->fetch($sql, [':id' => $id, ':title' => $id]);
        return $post ? $this->toFrontendResponse($post) : null;
    }
    
    public function getImageById($id) {
        $sql = "SELECT media FROM posts WHERE (id = :id OR post_title = :title) AND media IS NOT NULL LIMIT 1";
        $result = $this->db->fetch($sql, [':id' => $id, ':title' => $id]);
        return $result ? $result['media'] : null;
    }
    
    public function getPdfById($id) {
        $sql = "SELECT document FROM posts WHERE (id = :id OR post_title = :title) AND document IS NOT NULL LIMIT 1";
        $result = $this->db->fetch($sql, [':id' => $id, ':title' => $id]);
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
        
        return array_map([$this, 'toFrontendResponse'], $this->db->fetchAll($sql, [':user_id' => $userId]));
    }
    
    public function checkUserOwnership($postId, $userId) {
        $sql = "SELECT COUNT(*) as count FROM posts WHERE id = :id AND user_id = :user_id";
        $result = $this->db->fetch($sql, [':id' => $postId, ':user_id' => $userId]);
        return $result['count'] > 0;
    }

    public function search($query, $limit = 20, $offset = 0) {
        $like = '%' . $query . '%';
        $sql = "SELECT p.id, p.post_title, p.post_content, p.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       CASE WHEN p.media IS NOT NULL THEN true ELSE false END as has_media,
                       CASE WHEN p.document IS NOT NULL THEN true ELSE false END as has_document,
                       (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count
                FROM posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.post_title LIKE :title_query OR p.post_content LIKE :content_query
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset";

        return array_map([$this, 'toFrontendResponse'], $this->db->fetchAll($sql, [
            ':title_query' => $like,
            ':content_query' => $like,
            ':limit' => $limit,
            ':offset' => $offset
        ]));
    }

    public function like($postId, $userId) {
        return $this->db->execute(
            "INSERT IGNORE INTO likes (user_id, post_id) VALUES (:user_id, :post_id)",
            [':user_id' => $userId, ':post_id' => $postId]
        );
    }

    public function unlike($postId, $userId) {
        return $this->db->execute(
            "DELETE FROM likes WHERE user_id = :user_id AND post_id = :post_id",
            [':user_id' => $userId, ':post_id' => $postId]
        );
    }

    public function toFrontendResponse($post) {
        $title = $post['post_title'] ?? $post['postTitle'] ?? '';
        $content = $post['post_content'] ?? $post['postContent'] ?? '';
        $firstName = $post['first_name'] ?? $post['firstName'] ?? '';
        $lastName = $post['last_name'] ?? $post['lastName'] ?? '';

        return [
            'id' => $post['id'],
            'postTitle' => $title,
            'postContent' => $content,
            'content' => $content,
            'description' => $content,
            'createdAt' => $post['created_at'] ?? $post['createdAt'] ?? null,
            'userId' => isset($post['user_id']) ? (int)$post['user_id'] : null,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'author' => trim($firstName . ' ' . $lastName),
            'hasMedia' => (bool)($post['has_media'] ?? $post['hasMedia'] ?? false),
            'hasDocument' => (bool)($post['has_document'] ?? $post['hasDocument'] ?? false),
            'likes' => (int)($post['likes_count'] ?? $post['likes'] ?? 0),
            'views' => 0,
            'downloads' => 0,
            'bookmarks' => 0,
            'isLiked' => false,
            'isBookmarked' => false,
            'categories' => []
        ];
    }
}
