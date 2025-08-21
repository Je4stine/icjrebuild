<?php
class Chat {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getOrCreateConversation($user1Id, $user2Id) {
        // Ensure consistent ordering
        $userA = min($user1Id, $user2Id);
        $userB = max($user1Id, $user2Id);
        
        // Check if conversation exists
        $sql = "SELECT id FROM chat_conversations 
                WHERE user1_id = :user1_id AND user2_id = :user2_id";
        
        $conversation = $this->db->fetch($sql, [':user1_id' => $userA, ':user2_id' => $userB]);
        
        if ($conversation) {
            return $conversation['id'];
        }
        
        // Create new conversation
        $sql = "INSERT INTO chat_conversations (user1_id, user2_id) 
                VALUES (:user1_id, :user2_id)";
        
        $this->db->execute($sql, [':user1_id' => $userA, ':user2_id' => $userB]);
        return $this->db->lastInsertId();
    }
    
    public function sendMessage($conversationId, $senderId, $receiverId, $content, $messageType = 'TEXT') {
        $sql = "INSERT INTO chat_messages (conversation_id, sender_id, receiver_id, content, message_type) 
                VALUES (:conversation_id, :sender_id, :receiver_id, :content, :message_type)";
        
        $params = [
            ':conversation_id' => $conversationId,
            ':sender_id' => $senderId,
            ':receiver_id' => $receiverId,
            ':content' => $content,
            ':message_type' => $messageType
        ];
        
        $this->db->execute($sql, $params);
        $messageId = $this->db->lastInsertId();
        
        // Fetch the created message
        $fetchSql = "SELECT id, created_at FROM chat_messages WHERE id = :id";
        return $this->db->fetch($fetchSql, [':id' => $messageId]);
    }
    
    public function getMessages($conversationId, $limit = 50, $offset = 0) {
        $sql = "SELECT cm.id, cm.content, cm.message_type, cm.delivery_status, cm.created_at,
                       cm.sender_id, cm.receiver_id,
                       s.first_name as sender_first_name, s.last_name as sender_last_name,
                       r.first_name as receiver_first_name, r.last_name as receiver_last_name
                FROM chat_messages cm
                JOIN users s ON cm.sender_id = s.id
                JOIN users r ON cm.receiver_id = r.id
                WHERE cm.conversation_id = :conversation_id
                ORDER BY cm.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        return $this->db->fetchAll($sql, [
            ':conversation_id' => $conversationId,
            ':limit' => $limit,
            ':offset' => $offset
        ]);
    }
    
    public function getUserConversations($userId) {
        $sql = "SELECT DISTINCT cc.id, cc.created_at, cc.updated_at,
                       CASE 
                           WHEN cc.user1_id = :user_id THEN cc.user2_id 
                           ELSE cc.user1_id 
                       END as other_user_id,
                       CASE 
                           WHEN cc.user1_id = :user_id THEN u2.first_name 
                           ELSE u1.first_name 
                       END as other_user_first_name,
                       CASE 
                           WHEN cc.user1_id = :user_id THEN u2.last_name 
                           ELSE u1.last_name 
                       END as other_user_last_name,
                       (SELECT content FROM chat_messages cm 
                        WHERE cm.conversation_id = cc.id 
                        ORDER BY cm.created_at DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM chat_messages cm 
                        WHERE cm.conversation_id = cc.id 
                        ORDER BY cm.created_at DESC LIMIT 1) as last_message_time,
                       (SELECT COUNT(*) FROM chat_messages cm 
                        WHERE cm.conversation_id = cc.id 
                        AND cm.receiver_id = :user_id 
                        AND cm.read_at IS NULL) as unread_count
                FROM chat_conversations cc
                JOIN users u1 ON cc.user1_id = u1.id
                JOIN users u2 ON cc.user2_id = u2.id
                WHERE cc.user1_id = :user_id OR cc.user2_id = :user_id
                ORDER BY last_message_time DESC";
        
        return $this->db->fetchAll($sql, [':user_id' => $userId]);
    }
    
    public function markMessagesAsRead($conversationId, $userId) {
        $sql = "UPDATE chat_messages 
                SET read_at = CURRENT_TIMESTAMP 
                WHERE conversation_id = :conversation_id 
                AND receiver_id = :user_id 
                AND read_at IS NULL";
        
        return $this->db->execute($sql, [
            ':conversation_id' => $conversationId,
            ':user_id' => $userId
        ]);
    }
    
    public function getUnseenMessageCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM chat_messages 
                WHERE receiver_id = :user_id AND read_at IS NULL";
        
        $result = $this->db->fetch($sql, [':user_id' => $userId]);
        return $result['count'];
    }
    
    public function updateUserOnlineStatus($userId, $sessionId, $isOnline = true) {
        if ($isOnline) {
            $sql = "INSERT INTO user_connections (user_id, session_id, is_online, last_seen) 
                    VALUES (:user_id, :session_id, true, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE is_online = true, last_seen = CURRENT_TIMESTAMP";
        } else {
            $sql = "UPDATE user_connections 
                    SET is_online = false, last_seen = CURRENT_TIMESTAMP 
                    WHERE user_id = :user_id AND session_id = :session_id";
        }
        
        return $this->db->execute($sql, [
            ':user_id' => $userId,
            ':session_id' => $sessionId
        ]);
    }
    
    public function isUserOnline($userId) {
        $sql = "SELECT COUNT(*) as count FROM user_connections 
                WHERE user_id = :user_id AND is_online = true";
        
        $result = $this->db->fetch($sql, [':user_id' => $userId]);
        return $result['count'] > 0;
    }
}
