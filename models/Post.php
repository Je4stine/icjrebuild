<?php
class Post {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function buildBookmarkExpr($currentUserId, $postAlias = 'p') {
        return $currentUserId === null
            ? '0'
            : '(SELECT COUNT(*) > 0 FROM bookmarks b2 WHERE b2.post_id = ' . $postAlias . '.id AND b2.user_id = ' . (int)$currentUserId . ')';
    }

    private function buildLikeExpr($currentUserId, $postAlias = 'p') {
        return $currentUserId === null
            ? '0'
            : '(SELECT COUNT(*) > 0 FROM likes l2 WHERE l2.post_id = ' . $postAlias . '.id AND l2.user_id = ' . (int)$currentUserId . ')';
    }
    
    public function create($data, $mediaFile = null, $documentFile = null) {
        $this->db->beginTransaction();
        
        try {
            $sql = "INSERT INTO posts (id, post_title, post_content, category, user_id, media, document) 
                    VALUES (UUID(), :post_title, :post_content, :category, :user_id, :media, :document)";
            
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
                ':category' => $data['category'] ?? null,
                ':user_id' => $data['userId'],
                ':media' => $mediaData,
                ':document' => $documentData
            ];
            
            $this->db->execute($sql, $params);
            
            // Get the created post (MySQL doesn't support RETURNING with UUID, so we need to find it)
            $getPostSql = "SELECT id, post_title, post_content, category, created_at FROM posts 
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
    
    public function getAllPosts($page = 0, $pageSize = 10, $currentUserId = null) {
        $offset = $page * $pageSize;
        $bookmarkExpr = $this->buildBookmarkExpr($currentUserId);
        $likeExpr = $this->buildLikeExpr($currentUserId);

        $sql = "SELECT p.id, p.post_title, p.post_content, p.category, p.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       CASE WHEN p.media IS NOT NULL THEN true ELSE false END as has_media,
                       CASE WHEN p.document IS NOT NULL THEN true ELSE false END as has_document,
                       (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) as comment_count,
                       (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                       {$bookmarkExpr} as is_bookmarked,
                       {$likeExpr} as is_liked
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

    public function getCategoryCount($category) {
        $sql = "SELECT COUNT(*) as total FROM posts WHERE category = :category";
        $result = $this->db->fetch($sql, [':category' => $category]);
        return $result['total'];
    }

    public function getPostsByUserIdPaginated($userId, $page = 0, $pageSize = 10, $currentUserId = null, $filters = []) {
        $offset = $page * $pageSize;
        $search = trim($filters['search'] ?? '');
        $documentFilter = $filters['documentFilter'] ?? 'all';
        $sortBy = $filters['sortBy'] ?? 'newest';
        $bookmarkExpr = $this->buildBookmarkExpr($currentUserId);
        $likeExpr = $this->buildLikeExpr($currentUserId);
        $conditions = ['p.user_id = :user_id'];
        $params = [
            ':user_id' => $userId,
        ];

        if ($search !== '') {
            $conditions[] = '(p.post_title LIKE :search OR p.post_content LIKE :search OR p.category LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        if ($documentFilter === 'documents') {
            $conditions[] = 'p.document IS NOT NULL';
        } elseif ($documentFilter === 'posts') {
            $conditions[] = 'p.document IS NULL';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        $orderBy = 'p.created_at DESC';
        if ($sortBy === 'oldest') {
            $orderBy = 'p.created_at ASC';
        } elseif ($sortBy === 'title') {
            $orderBy = 'p.post_title ASC';
        } elseif ($sortBy === 'popular') {
            $orderBy = 'likes_count DESC, p.created_at DESC';
        }

        $sql = "SELECT p.id, p.post_title, p.post_content, p.category, p.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       CASE WHEN p.media IS NOT NULL THEN true ELSE false END as has_media,
                       CASE WHEN p.document IS NOT NULL THEN true ELSE false END as has_document,
                       (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) as comment_count,
                       (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                       {$bookmarkExpr} as is_bookmarked,
                       {$likeExpr} as is_liked
                FROM posts p
                JOIN users u ON p.user_id = u.id
                {$whereClause}
                ORDER BY {$orderBy}
                LIMIT :limit OFFSET :offset";

        $params[':limit'] = $pageSize;
        $params[':offset'] = $offset;

        return array_map([$this, 'toFrontendResponse'], $this->db->fetchAll($sql, $params));
    }

    public function getPostsByUserIdPaginatedCount($userId, $filters = []) {
        $search = trim($filters['search'] ?? '');
        $documentFilter = $filters['documentFilter'] ?? 'all';
        $conditions = ['p.user_id = :user_id'];
        $params = [':user_id' => $userId];

        if ($search !== '') {
            $conditions[] = '(p.post_title LIKE :search OR p.post_content LIKE :search OR p.category LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        if ($documentFilter === 'documents') {
            $conditions[] = 'p.document IS NOT NULL';
        } elseif ($documentFilter === 'posts') {
            $conditions[] = 'p.document IS NULL';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        $sql = "SELECT COUNT(*) as total
                FROM posts p
                JOIN users u ON p.user_id = u.id
                {$whereClause}";
        $result = $this->db->fetch($sql, $params);
        return $result['total'];
    }

    public function getBookmarkedPosts($userId, $page = 0, $pageSize = 10, $currentUserId = null, $filters = []) {
        $offset = $page * $pageSize;
        $search = trim($filters['search'] ?? '');
        $category = trim($filters['category'] ?? '');
        $sortBy = $filters['sortBy'] ?? 'recent';
        $bookmarkExpr = $this->buildBookmarkExpr($currentUserId);
        $likeExpr = $this->buildLikeExpr($currentUserId);
        $conditions = ['b.user_id = :user_id'];
        $params = [
            ':user_id' => $userId,
        ];

        if ($search !== '') {
            $conditions[] = '(p.post_title LIKE :search OR p.post_content LIKE :search OR p.category LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        if ($category !== '' && strtolower($category) !== 'all') {
            $conditions[] = 'LOWER(p.category) = LOWER(:category)';
            $params[':category'] = $category;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        $orderBy = 'b.created_at DESC';
        if ($sortBy === 'oldest') {
            $orderBy = 'b.created_at ASC';
        } elseif ($sortBy === 'title') {
            $orderBy = 'p.post_title ASC';
        } elseif ($sortBy === 'popular') {
            $orderBy = 'likes_count DESC, b.created_at DESC';
        }

        $sql = "SELECT p.id, p.post_title, p.post_content, p.category, p.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       CASE WHEN p.media IS NOT NULL THEN true ELSE false END as has_media,
                       CASE WHEN p.document IS NOT NULL THEN true ELSE false END as has_document,
                       (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) as comment_count,
                       (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                       {$bookmarkExpr} as is_bookmarked,
                       {$likeExpr} as is_liked
                FROM bookmarks b
                JOIN posts p ON p.id = b.post_id
                JOIN users u ON p.user_id = u.id
                {$whereClause}
                ORDER BY {$orderBy}
                LIMIT :limit OFFSET :offset";

        $params[':limit'] = $pageSize;
        $params[':offset'] = $offset;

        return array_map([$this, 'toFrontendResponse'], $this->db->fetchAll($sql, $params));
    }

    public function getBookmarkedPostsCount($userId, $filters = []) {
        $search = trim($filters['search'] ?? '');
        $category = trim($filters['category'] ?? '');
        $conditions = ['b.user_id = :user_id'];
        $params = [':user_id' => $userId];

        if ($search !== '') {
            $conditions[] = '(p.post_title LIKE :search OR p.post_content LIKE :search OR p.category LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        if ($category !== '' && strtolower($category) !== 'all') {
            $conditions[] = 'LOWER(p.category) = LOWER(:category)';
            $params[':category'] = $category;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        $sql = "SELECT COUNT(*) as total
                FROM bookmarks b
                JOIN posts p ON p.id = b.post_id
                JOIN users u ON p.user_id = u.id
                {$whereClause}";
        $result = $this->db->fetch($sql, $params);
        return $result['total'];
    }
    
    public function getById($id, $currentUserId = null) {
        $bookmarkExpr = $this->buildBookmarkExpr($currentUserId);
        $likeExpr = $this->buildLikeExpr($currentUserId);
        $sql = "SELECT p.id, p.post_title, p.post_content, p.category, p.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       CASE WHEN p.media IS NOT NULL THEN true ELSE false END as has_media,
                       CASE WHEN p.document IS NOT NULL THEN true ELSE false END as has_document,
                       (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) as comment_count,
                       (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                       {$bookmarkExpr} as is_bookmarked,
                       {$likeExpr} as is_liked
                FROM posts p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.id = :id OR p.post_title = :title
                LIMIT 1";
        
        $post = $this->db->fetch($sql, [
            ':id' => $id,
            ':title' => $id,
        ]);
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
    
    public function getPostsByUserId($userId, $currentUserId = null) {
        $bookmarkExpr = $this->buildBookmarkExpr($currentUserId);
        $likeExpr = $this->buildLikeExpr($currentUserId);
        $sql = "SELECT p.id, p.post_title, p.post_content, p.category, p.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       CASE WHEN p.media IS NOT NULL THEN true ELSE false END as has_media,
                       CASE WHEN p.document IS NOT NULL THEN true ELSE false END as has_document,
                       (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) as comment_count,
                       (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                       {$bookmarkExpr} as is_bookmarked,
                       {$likeExpr} as is_liked
                FROM posts p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.user_id = :user_id 
                ORDER BY p.created_at DESC";
        
        return array_map([$this, 'toFrontendResponse'], $this->db->fetchAll($sql, [
            ':user_id' => $userId
        ]));
    }
    
    public function checkUserOwnership($postId, $userId) {
        $sql = "SELECT COUNT(*) as count FROM posts WHERE id = :id AND user_id = :user_id";
        $result = $this->db->fetch($sql, [':id' => $postId, ':user_id' => $userId]);
        return $result['count'] > 0;
    }

    public function search($query, $limit = 20, $offset = 0, $currentUserId = null) {
        $like = '%' . $query . '%';
        $likeExpr = $this->buildLikeExpr($currentUserId);
        $sql = "SELECT p.id, p.post_title, p.post_content, p.category, p.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       CASE WHEN p.media IS NOT NULL THEN true ELSE false END as has_media,
                       CASE WHEN p.document IS NOT NULL THEN true ELSE false END as has_document,
                       (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) as comment_count,
                       (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                       {$likeExpr} as is_liked
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

    public function getByCategory($category, $page = 0, $pageSize = 100, $currentUserId = null) {
        $offset = $page * $pageSize;
        $likeExpr = $this->buildLikeExpr($currentUserId);
        $sql = "SELECT p.id, p.post_title, p.post_content, p.category, p.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       CASE WHEN p.media IS NOT NULL THEN true ELSE false END as has_media,
                       CASE WHEN p.document IS NOT NULL THEN true ELSE false END as has_document,
                       (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) as comment_count,
                       (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                       {$likeExpr} as is_liked
                FROM posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.category = :category
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset";

        return array_map([$this, 'toFrontendResponse'], $this->db->fetchAll($sql, [
            ':category' => $category,
            ':limit' => $pageSize,
            ':offset' => $offset
        ]));
    }

    public function searchByCategory($category, $query, $limit = 20, $offset = 0, $currentUserId = null) {
        $like = '%' . $query . '%';
        $likeExpr = $this->buildLikeExpr($currentUserId);
        $sql = "SELECT p.id, p.post_title, p.post_content, p.category, p.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       CASE WHEN p.media IS NOT NULL THEN true ELSE false END as has_media,
                       CASE WHEN p.document IS NOT NULL THEN true ELSE false END as has_document,
                       (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) as comment_count,
                       (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as likes_count,
                       {$likeExpr} as is_liked
                FROM posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.category = :category
                AND (p.post_title LIKE :title_query OR p.post_content LIKE :content_query)
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset";

        return array_map([$this, 'toFrontendResponse'], $this->db->fetchAll($sql, [
            ':category' => $category,
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

    public function bookmark($postId, $userId) {
        return $this->db->execute(
            "INSERT IGNORE INTO bookmarks (user_id, post_id) VALUES (:user_id, :post_id)",
            [':user_id' => $userId, ':post_id' => $postId]
        );
    }

    public function unlike($postId, $userId) {
        return $this->db->execute(
            "DELETE FROM likes WHERE user_id = :user_id AND post_id = :post_id",
            [':user_id' => $userId, ':post_id' => $postId]
        );
    }

    public function unbookmark($postId, $userId) {
        return $this->db->execute(
            "DELETE FROM bookmarks WHERE user_id = :user_id AND post_id = :post_id",
            [':user_id' => $userId, ':post_id' => $postId]
        );
    }

    public function hasLiked($postId, $userId) {
        $sql = "SELECT COUNT(*) as count FROM likes WHERE user_id = :user_id AND post_id = :post_id";
        $result = $this->db->fetch($sql, [':user_id' => $userId, ':post_id' => $postId]);
        return (int)($result['count'] ?? 0) > 0;
    }

    public function getLikeCount($postId) {
        $sql = "SELECT COUNT(*) as count FROM likes WHERE post_id = :post_id";
        $result = $this->db->fetch($sql, [':post_id' => $postId]);
        return (int)($result['count'] ?? 0);
    }

    public function createComment($postId, $userId, $content) {
        $sql = "INSERT INTO post_comments (id, post_id, user_id, comment, created_at)
                VALUES (:id, :post_id, :user_id, :comment, NOW())";

        $commentId = $this->generateUuid();
        $this->db->execute($sql, [
            ':id' => $commentId,
            ':post_id' => $postId,
            ':user_id' => $userId,
            ':comment' => $content
        ]);

        $comment = $this->db->fetch(
            "SELECT pc.id, pc.comment, pc.created_at, u.first_name, u.last_name, u.id as user_id
             FROM post_comments pc
             JOIN users u ON pc.user_id = u.id
             WHERE pc.id = :id
             LIMIT 1",
            [':id' => $commentId]
        );

        return $comment ? [
            'id' => $comment['id'],
            'comment' => $comment['comment'],
            'created_at' => $comment['created_at'],
            'user_id' => (int)$comment['user_id'],
            'first_name' => $comment['first_name'],
            'last_name' => $comment['last_name']
        ] : null;
    }

    public function getComments($postId) {
        $sql = "SELECT pc.id, pc.comment, pc.created_at, u.id as user_id, u.first_name, u.last_name
                FROM post_comments pc
                JOIN users u ON pc.user_id = u.id
                WHERE pc.post_id = :post_id
                ORDER BY pc.created_at ASC";

        return array_map(function ($comment) {
            return [
                'id' => $comment['id'],
                'content' => $comment['comment'],
                'createdAt' => $comment['created_at'],
                'userId' => (int)$comment['user_id'],
                'author' => [
                    'firstName' => $comment['first_name'],
                    'lastName' => $comment['last_name']
                ],
                'likes' => 0
            ];
        }, $this->db->fetchAll($sql, [':post_id' => $postId]));
    }

    public function toFrontendResponse($post) {
        $title = $post['post_title'] ?? $post['postTitle'] ?? '';
        $content = $post['post_content'] ?? $post['postContent'] ?? '';
        $category = $post['category'] ?? null;
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
            'commentCount' => (int)($post['comment_count'] ?? $post['commentCount'] ?? 0),
            'likes' => (int)($post['likes_count'] ?? $post['likes'] ?? 0),
            'views' => 0,
            'downloads' => 0,
            'bookmarks' => 0,
            'isLiked' => (bool)($post['is_liked'] ?? $post['isLiked'] ?? false),
            'isBookmarked' => (bool)($post['is_bookmarked'] ?? $post['isBookmarked'] ?? false),
            'category' => $category,
            'categories' => $category ? [$category] : []
        ];
    }

    private function generateUuid() {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
