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
        $sql = "INSERT INTO chat_conversations (id, user1_id, user2_id) 
                VALUES (UUID(), :user1_id, :user2_id)";
        
        $this->db->execute($sql, [':user1_id' => $userA, ':user2_id' => $userB]);

        $created = $this->db->fetch(
            "SELECT id FROM chat_conversations WHERE user1_id = :user1_id AND user2_id = :user2_id",
            [':user1_id' => $userA, ':user2_id' => $userB]
        );

        return $created['id'];
    }
    
    public function sendMessage($conversationId, $senderId, $receiverId, $content, $messageType = 'TEXT') {
        $sql = "INSERT INTO chat_messages (id, conversation_id, sender_id, receiver_id, content, message_type) 
                VALUES (UUID(), :conversation_id, :sender_id, :receiver_id, :content, :message_type)";
        
        $params = [
            ':conversation_id' => $conversationId,
            ':sender_id' => $senderId,
            ':receiver_id' => $receiverId,
            ':content' => $content,
            ':message_type' => $messageType
        ];
        
        $this->db->execute($sql, $params);

        $fetchSql = "SELECT id, created_at
                     FROM chat_messages
                     WHERE conversation_id = :conversation_id AND sender_id = :sender_id
                     ORDER BY created_at DESC LIMIT 1";
        return $this->db->fetch($fetchSql, [
            ':conversation_id' => $conversationId,
            ':sender_id' => $senderId
        ]);
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
        
        return array_map([$this, 'toMessageResponse'], $this->db->fetchAll($sql, [
            ':conversation_id' => $conversationId,
            ':limit' => $limit,
            ':offset' => $offset
        ]));
    }
    
    public function getUserConversations($userId) {
        $sql = "SELECT DISTINCT cc.id, cc.created_at, cc.updated_at,
                       CASE 
                           WHEN cc.user1_id = :case_user_id_1 THEN cc.user2_id 
                           ELSE cc.user1_id 
                       END as other_user_id,
                       CASE 
                           WHEN cc.user1_id = :case_user_id_2 THEN u2.first_name 
                           ELSE u1.first_name 
                       END as other_user_first_name,
                       CASE 
                           WHEN cc.user1_id = :case_user_id_3 THEN u2.last_name 
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
                        AND cm.receiver_id = :receiver_user_id 
                        AND cm.read_at IS NULL) as unread_count
                FROM chat_conversations cc
                JOIN users u1 ON cc.user1_id = u1.id
                JOIN users u2 ON cc.user2_id = u2.id
                WHERE cc.user1_id = :where_user_id_1 OR cc.user2_id = :where_user_id_2
                ORDER BY last_message_time DESC";
        
        return array_map([$this, 'toConversationResponse'], $this->db->fetchAll($sql, [
            ':case_user_id_1' => $userId,
            ':case_user_id_2' => $userId,
            ':case_user_id_3' => $userId,
            ':receiver_user_id' => $userId,
            ':where_user_id_1' => $userId,
            ':where_user_id_2' => $userId
        ]));
    }

    public function getConversationById($conversationId) {
        return $this->db->fetch(
            "SELECT * FROM chat_conversations WHERE id = :id",
            [':id' => $conversationId]
        );
    }

    public function getOtherParticipantId($conversationId, $currentUserId) {
        $conversation = $this->getConversationById($conversationId);
        if (!$conversation) {
            return null;
        }

        if ((int)$conversation['user1_id'] === (int)$currentUserId) {
            return (int)$conversation['user2_id'];
        }

        if ((int)$conversation['user2_id'] === (int)$currentUserId) {
            return (int)$conversation['user1_id'];
        }

        return null;
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

    private function toConversationResponse($conversation) {
        $participantName = trim(($conversation['other_user_first_name'] ?? '') . ' ' . ($conversation['other_user_last_name'] ?? ''));

        return [
            'id' => $conversation['id'],
            'participantId' => isset($conversation['other_user_id']) ? (int)$conversation['other_user_id'] : null,
            'participantName' => $participantName,
            'participantAvatar' => null,
            'lastMessage' => $conversation['last_message'] ?? '',
            'lastMessageTime' => $conversation['last_message_time'] ?? $conversation['updated_at'] ?? $conversation['created_at'],
            'unreadCount' => (int)($conversation['unread_count'] ?? 0),
            'isOnline' => false,
            'createdAt' => $conversation['created_at'] ?? null,
            'updatedAt' => $conversation['updated_at'] ?? null
        ];
    }

    private function toMessageResponse($message) {
        return [
            'id' => $message['id'],
            'conversationId' => $message['conversation_id'] ?? null,
            'senderId' => isset($message['sender_id']) ? (int)$message['sender_id'] : null,
            'receiverId' => isset($message['receiver_id']) ? (int)$message['receiver_id'] : null,
            'senderName' => trim(($message['sender_first_name'] ?? '') . ' ' . ($message['sender_last_name'] ?? '')),
            'receiverName' => trim(($message['receiver_first_name'] ?? '') . ' ' . ($message['receiver_last_name'] ?? '')),
            'content' => $message['content'],
            'messageType' => strtolower($message['message_type'] ?? 'text'),
            'type' => strtolower($message['message_type'] ?? 'text'),
            'timestamp' => $message['created_at'] ?? null,
            'createdAt' => $message['created_at'] ?? null,
            'isRead' => ($message['delivery_status'] ?? '') === 'READ'
        ];
    }
}
