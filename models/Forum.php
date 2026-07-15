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
    
    public function getAllForums($page = 0, $pageSize = 10, $search = '', $category = '') {
        $offset = $page * $pageSize;
        $conditions = [];
        $params = [
            ':limit' => $pageSize,
            ':offset' => $offset
        ];

        if ($category !== '' && strtolower($category) !== 'all') {
            $conditions[] = 'f.topic = :category';
            $params[':category'] = $category;
        }

        if ($search !== '') {
            $conditions[] = '(f.forum_name LIKE :search OR f.topic LIKE :search OR f.description LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT f.id, f.forum_name, f.topic, f.description, f.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       (SELECT COUNT(*) FROM forum_memberships fm WHERE fm.forum_id = f.id) as member_count,
                       (SELECT COUNT(*) FROM conversations c WHERE c.forum_id = f.id) as discussion_count,
                       (SELECT COUNT(*) FROM forum_comments fc WHERE fc.forum_id = f.id) as comment_count
                FROM discussion_forums f 
                JOIN users u ON f.user_id = u.id 
                {$whereClause}
                ORDER BY f.created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        return array_map([$this, 'toFrontendResponse'], $this->db->fetchAll($sql, $params));
    }

    public function getTotalCount($search = '', $category = '') {
        $conditions = [];
        $params = [];

        if ($category !== '' && strtolower($category) !== 'all') {
            $conditions[] = 'topic = :category';
            $params[':category'] = $category;
        }

        if ($search !== '') {
            $conditions[] = '(forum_name LIKE :search OR topic LIKE :search OR description LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT COUNT(*) as total FROM discussion_forums {$whereClause}";
        $result = $this->db->fetch($sql, $params);
        return $result['total'];
    }
    
    public function getById($id) {
        $sql = "SELECT f.id, f.forum_name, f.topic, f.description, f.created_at,
                       u.id as user_id, u.first_name, u.last_name,
                       (SELECT COUNT(*) FROM forum_memberships fm WHERE fm.forum_id = f.id) as member_count,
                       (SELECT COUNT(*) FROM conversations c WHERE c.forum_id = f.id) as discussion_count,
                       (SELECT COUNT(*) FROM forum_comments fc WHERE fc.forum_id = f.id) as comment_count
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
    
    public function getAllDiscussions($forumId, $userId = null) {
        $sql = "SELECT c.id, c.author, c.content, c.created_at,
                       (SELECT COUNT(*) FROM replies r WHERE r.conversation_id = c.id) as reply_count,
                       (SELECT COUNT(*) FROM forum_likes fl WHERE fl.target_type = 'conversation' AND fl.target_id = c.id) as like_count,
                       CASE WHEN :current_user_id IS NULL THEN 0 ELSE EXISTS(
                           SELECT 1 FROM forum_likes fl2
                           WHERE fl2.target_type = 'conversation' AND fl2.target_id = c.id AND fl2.user_id = :current_user_id_check
                       ) END as is_liked
                FROM conversations c
                WHERE c.forum_id = :forum_id
                ORDER BY c.created_at DESC";
        
        return array_map([$this, 'toDiscussionResponse'], $this->db->fetchAll($sql, [
            ':forum_id' => $forumId,
            ':current_user_id' => $userId,
            ':current_user_id_check' => $userId
        ]));
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
    
    public function getRepliesForConversation($conversationId, $userId = null) {
        $flatReplies = $this->getAllRepliesForConversation($conversationId, $userId);
        return $this->buildReplyTree($flatReplies);
    }
    
    public function getNestedReplies($parentId, $userId = null) {
        $reply = $this->db->fetch("SELECT conversation_id FROM replies WHERE id = :id", [':id' => $parentId]);
        if (!$reply) {
            return [];
        }

        $flatReplies = $this->getAllRepliesForConversation($reply['conversation_id'], $userId);
        $tree = $this->buildReplyTree($flatReplies, $parentId);
        return $tree;
    }

    public function likeTarget($targetType, $targetId, $userId) {
        $this->assertLikeTargetExists($targetType, $targetId);

        return $this->db->execute(
            "INSERT IGNORE INTO forum_likes (user_id, target_type, target_id)
             VALUES (:user_id, :target_type, :target_id)",
            [':user_id' => $userId, ':target_type' => $targetType, ':target_id' => $targetId]
        );
    }

    public function hasLikedTarget($targetType, $targetId, $userId) {
        $sql = "SELECT COUNT(*) as count FROM forum_likes
                WHERE user_id = :user_id AND target_type = :target_type AND target_id = :target_id";

        $result = $this->db->fetch($sql, [
            ':user_id' => $userId,
            ':target_type' => $targetType,
            ':target_id' => $targetId
        ]);

        return (int)($result['count'] ?? 0) > 0;
    }

    public function unlikeTarget($targetType, $targetId, $userId) {
        return $this->db->execute(
            "DELETE FROM forum_likes WHERE user_id = :user_id AND target_type = :target_type AND target_id = :target_id",
            [':user_id' => $userId, ':target_type' => $targetType, ':target_id' => $targetId]
        );
    }

    public function getLikeSummary($targetType, $targetId, $userId = null) {
        $count = $this->db->fetch(
            "SELECT COUNT(*) as count FROM forum_likes WHERE target_type = :target_type AND target_id = :target_id",
            [':target_type' => $targetType, ':target_id' => $targetId]
        );

        $liked = false;
        if ($userId) {
            $likedRow = $this->db->fetch(
                "SELECT COUNT(*) as count FROM forum_likes WHERE user_id = :user_id AND target_type = :target_type AND target_id = :target_id",
                [':user_id' => $userId, ':target_type' => $targetType, ':target_id' => $targetId]
            );
            $liked = (int)($likedRow['count'] ?? 0) > 0;
        }

        return [
            'targetType' => $targetType,
            'targetId' => $targetId,
            'likeCount' => (int)($count['count'] ?? 0),
            'isLiked' => $liked
        ];
    }

    private function getAllRepliesForConversation($conversationId, $userId = null) {
        $sql = "SELECT r.id, r.content, r.author, r.created_at, r.parent_id, r.conversation_id,
                       (SELECT COUNT(*) FROM replies r2 WHERE r2.parent_id = r.id) as nested_count,
                       (SELECT COUNT(*) FROM forum_likes fl WHERE fl.target_type = 'reply' AND fl.target_id = r.id) as like_count,
                       CASE WHEN :current_user_id IS NULL THEN 0 ELSE EXISTS(
                           SELECT 1 FROM forum_likes fl2
                           WHERE fl2.target_type = 'reply' AND fl2.target_id = r.id AND fl2.user_id = :current_user_id_check
                       ) END as is_liked
                FROM replies r
                WHERE r.conversation_id = :conversation_id
                ORDER BY r.created_at ASC";

        return array_map([$this, 'toReplyResponse'], $this->db->fetchAll($sql, [
            ':conversation_id' => $conversationId,
            ':current_user_id' => $userId,
            ':current_user_id_check' => $userId
        ]));
    }

    private function buildReplyTree($replies, $parentId = null) {
        $childrenByParent = [];
        foreach ($replies as $reply) {
            $key = $reply['parentId'] ?? '__root__';
            $childrenByParent[$key][] = $reply;
        }

        $build = function ($currentParentId) use (&$build, &$childrenByParent) {
            $key = $currentParentId ?? '__root__';
            $children = $childrenByParent[$key] ?? [];
            return array_map(function ($reply) use (&$build) {
                $reply['children'] = $build($reply['id']);
                $reply['nestedCount'] = count($reply['children']);
                return $reply;
            }, $children);
        };

        return $build($parentId);
    }

    private function assertLikeTargetExists($targetType, $targetId) {
        if ($targetType === 'conversation') {
            $row = $this->db->fetch("SELECT id FROM conversations WHERE id = :id", [':id' => $targetId]);
        } elseif ($targetType === 'reply') {
            $row = $this->db->fetch("SELECT id FROM replies WHERE id = :id", [':id' => $targetId]);
        } else {
            throw new Exception('Invalid forum like target type');
        }

        if (!$row) {
            throw new Exception('Forum like target not found');
        }
    }

    private function toDiscussionResponse($discussion) {
        return [
            'id' => $discussion['id'],
            'author' => $discussion['author'],
            'content' => $discussion['content'],
            'createdAt' => $discussion['created_at'] ?? null,
            'created_at' => $discussion['created_at'] ?? null,
            'replyCount' => (int)($discussion['reply_count'] ?? 0),
            'reply_count' => (int)($discussion['reply_count'] ?? 0),
            'likeCount' => (int)($discussion['like_count'] ?? 0),
            'like_count' => (int)($discussion['like_count'] ?? 0),
            'isLiked' => (bool)($discussion['is_liked'] ?? false)
        ];
    }

    private function toReplyResponse($reply) {
        return [
            'id' => $reply['id'],
            'content' => $reply['content'],
            'author' => $reply['author'],
            'createdAt' => $reply['created_at'] ?? null,
            'created_at' => $reply['created_at'] ?? null,
            'conversationId' => $reply['conversation_id'] ?? null,
            'conversation_id' => $reply['conversation_id'] ?? null,
            'parentId' => $reply['parent_id'] ?? null,
            'parent_id' => $reply['parent_id'] ?? null,
            'nestedCount' => (int)($reply['nested_count'] ?? 0),
            'nested_count' => (int)($reply['nested_count'] ?? 0),
            'likeCount' => (int)($reply['like_count'] ?? 0),
            'like_count' => (int)($reply['like_count'] ?? 0),
            'isLiked' => (bool)($reply['is_liked'] ?? false),
            'children' => []
        ];
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
            'discussionCount' => (int)($forum['discussion_count'] ?? $forum['discussionCount'] ?? $forum['discussions'] ?? 0),
            'discussion_count' => (int)($forum['discussion_count'] ?? $forum['discussionCount'] ?? $forum['discussions'] ?? 0),
            'discussions' => (int)($forum['discussion_count'] ?? $forum['discussionCount'] ?? $forum['discussions'] ?? 0),
            'commentCount' => (int)($forum['comment_count'] ?? $forum['commentCount'] ?? 0),
            'comment_count' => (int)($forum['comment_count'] ?? $forum['commentCount'] ?? 0)
        ];
    }
}
