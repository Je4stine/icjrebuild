<?php
class Forum {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function create($data, $userId) {
        $sql = "INSERT INTO discussion_forums (forum_name, topic, description, user_id, created_at) 
                VALUES (:forum_name, :topic, :description, :user_id, NOW())";
        
        $params = [
            ':forum_name' => $data['forumName'],
            ':topic' => $data['topic'],
            ':description' => $data['description'],
            ':user_id' => $userId
        ];
        
        $this->db->execute($sql, $params);
        $forumId = $this->db->lastInsertId();
        
        // Fetch the created forum
        $fetchSql = "SELECT id, forum_name, topic, description, created_at FROM discussion_forums WHERE id = :id";
        return $this->db->fetch($fetchSql, [':id' => $forumId]);
    }
    
    public function getAllForums($page = 0, $pageSize = 10) {
        $offset = $page * $pageSize;
        
        $sql = "SELECT f.id, f.forum_name, f.topic, f.description, f.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       (SELECT COUNT(*) FROM forum_memberships fm WHERE fm.forum_id = f.id) as member_count,
                       (SELECT COUNT(*) FROM forum_comments fc WHERE fc.forum_id = f.id) as comment_count
                FROM discussion_forums f 
                JOIN users u ON f.user_id = u.id 
                ORDER BY f.created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        return $this->db->fetchAll($sql, [':limit' => $pageSize, ':offset' => $offset]);
    }
    
    public function getTotalCount() {
        $sql = "SELECT COUNT(*) as total FROM discussion_forums";
        $result = $this->db->fetch($sql);
        return $result['total'];
    }
    
    public function getById($id) {
        $sql = "SELECT f.id, f.forum_name, f.topic, f.description, f.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       (SELECT COUNT(*) FROM forum_memberships fm WHERE fm.forum_id = f.id) as member_count
                FROM discussion_forums f 
                JOIN users u ON f.user_id = u.id 
                WHERE f.id = :id";
        
        return $this->db->fetch($sql, [':id' => $id]);
    }
    
    public function joinForum($forumId, $userId) {
        // Check if user is already a member
        if ($this->isUserMember($forumId, $userId)) {
            throw new Exception('User is already a member of this forum');
        }
        
        $sql = "INSERT INTO forum_memberships (forum_id, user_id, joined_at) 
                VALUES (:forum_id, :user_id, NOW())";
        
        return $this->db->execute($sql, [':forum_id' => $forumId, ':user_id' => $userId]);
    }
    
    public function isUserMember($forumId, $userId) {
        $sql = "SELECT COUNT(*) as count FROM forum_memberships 
                WHERE forum_id = :forum_id AND user_id = :user_id";
        
        $result = $this->db->fetch($sql, [':forum_id' => $forumId, ':user_id' => $userId]);
        return $result['count'] > 0;
    }
    
    public function getMemberCount($forumId) {
        $sql = "SELECT COUNT(*) as count FROM forum_memberships WHERE forum_id = :forum_id";
        $result = $this->db->fetch($sql, [':forum_id' => $forumId]);
        return $result['count'];
    }
    
    public function getForumsByUser($userId) {
        $sql = "SELECT f.id, f.forum_name, f.topic, f.description, fm.joined_at
                FROM discussion_forums f 
                JOIN forum_memberships fm ON f.id = fm.forum_id 
                WHERE fm.user_id = :user_id 
                ORDER BY fm.joined_at DESC";
        
        return $this->db->fetchAll($sql, [':user_id' => $userId]);
    }
    
    public function createComment($data, $userId, $forumId) {
        $sql = "INSERT INTO forum_comments (comment, user_id, forum_id, created_at) 
                VALUES (:comment, :user_id, :forum_id, NOW())";
        
        $params = [
            ':comment' => $data['comment'],
            ':user_id' => $userId,
            ':forum_id' => $forumId
        ];
        
        $this->db->execute($sql, $params);
        $commentId = $this->db->lastInsertId();
        
        // Fetch the created comment
        $fetchSql = "SELECT id, comment, created_at FROM forum_comments WHERE id = :id";
        return $this->db->fetch($fetchSql, [':id' => $commentId]);
    }
    
    public function getComments($forumId) {
        $sql = "SELECT fc.id, fc.comment, fc.created_at,
                       u.id as user_id, u.first_name, u.last_name
                FROM forum_comments fc 
                JOIN users u ON fc.user_id = u.id 
                WHERE fc.forum_id = :forum_id 
                ORDER BY fc.created_at ASC";
        
        return $this->db->fetchAll($sql, [':forum_id' => $forumId]);
    }
    
    public function createDiscussion($data, $userId, $forumId) {
        $sql = "INSERT INTO conversations (author, content, forum_id, created_at) 
                VALUES (:author, :content, :forum_id, NOW())";
        
        $params = [
            ':author' => $data['author'],
            ':content' => $data['content'],
            ':forum_id' => $forumId
        ];
        
        $this->db->execute($sql, $params);
        $discussionId = $this->db->lastInsertId();
        
        // Fetch the created discussion
        $fetchSql = "SELECT id, author, content, created_at FROM conversations WHERE id = :id";
        return $this->db->fetch($fetchSql, [':id' => $discussionId]);
    }
    
    public function getAllDiscussions($forumId) {
        $sql = "SELECT c.id, c.author, c.content, c.created_at,
                       (SELECT COUNT(*) FROM replies r WHERE r.conversation_id = c.id) as reply_count
                FROM conversations c 
                WHERE c.forum_id = :forum_id 
                ORDER BY c.created_at DESC";
        
        return $this->db->fetchAll($sql, [':forum_id' => $forumId]);
    }
    
    public function createReply($data, $conversationId, $parentId = null) {
        $sql = "INSERT INTO replies (content, author, conversation_id, parent_id, created_at) 
                VALUES (:content, :author, :conversation_id, :parent_id, NOW())";
        
        $params = [
            ':content' => $data['content'],
            ':author' => $data['author'],
            ':conversation_id' => $conversationId,
            ':parent_id' => $parentId
        ];
        
        $this->db->execute($sql, $params);
        $replyId = $this->db->lastInsertId();
        
        // Fetch the created reply
        $fetchSql = "SELECT id, content, author, created_at FROM replies WHERE id = :id";
        return $this->db->fetch($fetchSql, [':id' => $replyId]);
    }
    
    public function getRepliesForConversation($conversationId) {
        $sql = "SELECT r.id, r.content, r.author, r.created_at, r.parent_id,
                       (SELECT COUNT(*) FROM replies r2 WHERE r2.parent_id = r.id) as nested_count
                FROM replies r 
                WHERE r.conversation_id = :conversation_id AND r.parent_id IS NULL
                ORDER BY r.created_at ASC";
        
        return $this->db->fetchAll($sql, [':conversation_id' => $conversationId]);
    }
    
    public function getNestedReplies($parentId) {
        $sql = "SELECT r.id, r.content, r.author, r.created_at, r.parent_id
                FROM replies r 
                WHERE r.parent_id = :parent_id 
                ORDER BY r.created_at ASC";
        
        return $this->db->fetchAll($sql, [':parent_id' => $parentId]);
    }
}
