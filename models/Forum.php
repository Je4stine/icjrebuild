<?php
class Forum {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function create($data, $userId) {
        $sql = "INSERT INTO discussion_forums (id, forum_name, topic, description, user_id, created_at) 
                VALUES (UUID(), :forum_name, :topic, :description, :user_id, NOW())";
        
        $params = [
            ':forum_name' => $data['forumName'],
            ':topic' => $data['topic'],
            ':description' => $data['description'],
            ':user_id' => $userId
        ];
        
        $this->db->execute($sql, $params);

        $fetchSql = "SELECT id, forum_name, topic, description, created_at
                     FROM discussion_forums
                     WHERE user_id = :user_id AND forum_name = :forum_name
                     ORDER BY created_at DESC LIMIT 1";
        return $this->db->fetch($fetchSql, [
            ':user_id' => $userId,
            ':forum_name' => $data['forumName']
        ]);
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
        
        return array_map([$this, 'toFrontendResponse'], $this->db->fetchAll($sql, [':limit' => $pageSize, ':offset' => $offset]));
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
        
        $forum = $this->db->fetch($sql, [':id' => $id]);
        return $forum ? $this->toFrontendResponse($forum) : null;
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
        $sql = "INSERT INTO forum_comments (id, comment, user_id, forum_id, created_at) 
                VALUES (UUID(), :comment, :user_id, :forum_id, NOW())";
        
        $params = [
            ':comment' => $data['comment'],
            ':user_id' => $userId,
            ':forum_id' => $forumId
        ];
        
        $this->db->execute($sql, $params);

        $fetchSql = "SELECT id, comment, created_at
                     FROM forum_comments
                     WHERE user_id = :user_id AND forum_id = :forum_id
                     ORDER BY created_at DESC LIMIT 1";
        return $this->db->fetch($fetchSql, [':user_id' => $userId, ':forum_id' => $forumId]);
    }
    
    public function getComments($forumId) {
        $sql = "SELECT fc.id, fc.comment, fc.created_at,
                       u.id as user_id, u.first_name, u.last_name
                FROM forum_comments fc 
                JOIN users u ON fc.user_id = u.id 
                WHERE fc.forum_id = :forum_id 
                ORDER BY fc.created_at ASC";
        
        return array_map(function ($discussion) use ($forumId) {
            return [
                'id' => $discussion['id'],
                'author' => $discussion['author'],
                'content' => $discussion['content'],
                'createdAt' => $discussion['created_at'],
                'forumId' => $forumId,
                'replyCount' => (int)($discussion['reply_count'] ?? 0)
            ];
        }, $this->db->fetchAll($sql, [':forum_id' => $forumId]));
    }
    
    public function createDiscussion($data, $userId, $forumId) {
        $sql = "INSERT INTO conversations (id, author, content, forum_id, created_at) 
                VALUES (UUID(), :author, :content, :forum_id, NOW())";
        
        $params = [
            ':author' => $data['author'],
            ':content' => $data['content'],
            ':forum_id' => $forumId
        ];
        
        $this->db->execute($sql, $params);

        $fetchSql = "SELECT id, author, content, created_at
                     FROM conversations
                     WHERE forum_id = :forum_id AND author = :author
                     ORDER BY created_at DESC LIMIT 1";
        return $this->db->fetch($fetchSql, [':forum_id' => $forumId, ':author' => $data['author']]);
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
        $sql = "INSERT INTO replies (id, content, author, conversation_id, parent_id, created_at) 
                VALUES (UUID(), :content, :author, :conversation_id, :parent_id, NOW())";
        
        $params = [
            ':content' => $data['content'],
            ':author' => $data['author'],
            ':conversation_id' => $conversationId,
            ':parent_id' => $parentId
        ];
        
        $this->db->execute($sql, $params);

        $fetchSql = "SELECT id, content, author, created_at
                     FROM replies
                     WHERE conversation_id = :conversation_id AND author = :author
                     ORDER BY created_at DESC LIMIT 1";
        return $this->db->fetch($fetchSql, [':conversation_id' => $conversationId, ':author' => $data['author']]);
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

    public function toFrontendResponse($forum) {
        return [
            'id' => $forum['id'],
            'forumName' => $forum['forum_name'] ?? $forum['forumName'] ?? '',
            'topic' => $forum['topic'] ?? '',
            'description' => $forum['description'] ?? '',
            'createdAt' => $forum['created_at'] ?? null,
            'userId' => isset($forum['user_id']) ? (int)$forum['user_id'] : null,
            'firstName' => $forum['first_name'] ?? '',
            'lastName' => $forum['last_name'] ?? '',
            'members' => (int)($forum['member_count'] ?? $forum['members'] ?? 0),
            'memberCount' => (int)($forum['member_count'] ?? $forum['memberCount'] ?? 0),
            'commentCount' => (int)($forum['comment_count'] ?? $forum['commentCount'] ?? 0)
        ];
    }
}
