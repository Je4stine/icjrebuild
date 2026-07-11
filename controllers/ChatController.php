<?php
class ChatController {
    private $chatModel;
    private $userModel;
    
    public function __construct() {
        $this->chatModel = new Chat();
        $this->userModel = new User();
    }
    
    public function getConversations() {
        try {
            // Get current user
            $currentUserEmail = $GLOBALS['current_user_email'] ?? null;
            if (!$currentUserEmail) {
                ResponseHelper::error('User not authenticated', 401);
            }
            
            $currentUser = $this->userModel->findByEmail($currentUserEmail);
            if (!$currentUser) {
                ResponseHelper::error('User not found', 404);
            }
            
            $conversations = $this->chatModel->getUserConversations($currentUser['id']);
            ResponseHelper::success([
                'conversations' => $conversations,
                'content' => $conversations
            ]);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get conversations: ' . $e->getMessage(), 500);
        }
    }
    
    public function sendMessage() {
        try {
            // Get current user
            $currentUserEmail = $GLOBALS['current_user_email'] ?? null;
            if (!$currentUserEmail) {
                ResponseHelper::error('User not authenticated', 401);
            }
            
            $currentUser = $this->userModel->findByEmail($currentUserEmail);
            if (!$currentUser) {
                ResponseHelper::error('User not found', 404);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['content']) || empty($input['receiverId'])) {
                ResponseHelper::error('Content and receiver ID are required', 400);
            }
            
            $receiverId = $input['receiverId'];
            $content = $input['content'];
            $messageType = $input['messageType'] ?? 'TEXT';
            
            // Check if receiver exists
            $receiver = $this->userModel->findByEmail($input['receiverEmail'] ?? '');
            if (!$receiver && $receiverId) {
                // Try to find by ID if email not provided
                $sql = "SELECT * FROM users WHERE id = :id";
                $db = Database::getInstance();
                $receiver = $db->fetch($sql, [':id' => $receiverId]);
            }
            
            if (!$receiver) {
                ResponseHelper::error('Receiver not found', 404);
            }
            
            // Get or create conversation
            $conversationId = $this->chatModel->getOrCreateConversation($currentUser['id'], $receiver['id']);
            
            // Send message
            $message = $this->chatModel->sendMessage(
                $conversationId, 
                $currentUser['id'], 
                $receiver['id'], 
                $content, 
                $messageType
            );
            
            $response = [
                'id' => $message['id'],
                'conversationId' => $conversationId,
                'senderId' => $currentUser['id'],
                'receiverId' => $receiver['id'],
                'content' => $content,
                'messageType' => $messageType,
                'createdAt' => $message['created_at'],
                'senderUsername' => $currentUser['first_name'] . ' ' . $currentUser['last_name'],
                'receiverUsername' => $receiver['first_name'] . ' ' . $receiver['last_name']
            ];
            
            ResponseHelper::success($response, 'Message sent successfully', 201);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to send message: ' . $e->getMessage(), 500);
        }
    }

    public function createConversation() {
        try {
            $currentUser = $this->getCurrentUser();
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $participantId = $input['participantId'] ?? null;

            if (!$participantId) {
                ResponseHelper::error('Participant ID is required', 400);
            }

            if ((int)$participantId === (int)$currentUser['id']) {
                ResponseHelper::error('Cannot create a conversation with yourself', 400);
            }

            $conversationId = $this->chatModel->getOrCreateConversation($currentUser['id'], (int)$participantId);

            ResponseHelper::success([
                'conversation' => [
                    'id' => $conversationId,
                    'participantId' => (int)$participantId,
                    'lastMessage' => '',
                    'lastMessageTime' => date('c'),
                    'unreadCount' => 0,
                    'isOnline' => false
                ],
                'content' => [
                    'id' => $conversationId,
                    'participantId' => (int)$participantId
                ]
            ], 'Conversation created', 201);
        } catch (Exception $e) {
            ResponseHelper::error('Failed to create conversation: ' . $e->getMessage(), 500);
        }
    }
    
    public function getMessages($conversationId) {
        try {
            // Get current user
            $currentUserEmail = $GLOBALS['current_user_email'] ?? null;
            if (!$currentUserEmail) {
                ResponseHelper::error('User not authenticated', 401);
            }
            
            $currentUser = $this->userModel->findByEmail($currentUserEmail);
            if (!$currentUser) {
                ResponseHelper::error('User not found', 404);
            }
            
            $limit = intval($_GET['limit'] ?? 50);
            $offset = intval($_GET['offset'] ?? 0);
            
            $messages = $this->chatModel->getMessages($conversationId, $limit, $offset);
            
            // Mark messages as read
            $this->chatModel->markMessagesAsRead($conversationId, $currentUser['id']);
            
            ResponseHelper::success([
                'messages' => $messages,
                'content' => $messages
            ]);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get messages: ' . $e->getMessage(), 500);
        }
    }

    public function sendConversationMessage($conversationId) {
        try {
            $currentUser = $this->getCurrentUser();
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $content = trim($input['content'] ?? '');

            if ($content === '') {
                ResponseHelper::error('Content is required', 400);
            }

            $receiverId = $this->chatModel->getOtherParticipantId($conversationId, $currentUser['id']);
            if (!$receiverId) {
                ResponseHelper::error('Conversation not found', 404);
            }

            $message = $this->chatModel->sendMessage(
                $conversationId,
                $currentUser['id'],
                $receiverId,
                $content,
                strtoupper($input['type'] ?? $input['messageType'] ?? 'TEXT')
            );

            $response = [
                'id' => $message['id'],
                'conversationId' => $conversationId,
                'senderId' => (int)$currentUser['id'],
                'receiverId' => (int)$receiverId,
                'senderName' => trim($currentUser['first_name'] . ' ' . $currentUser['last_name']),
                'content' => $content,
                'messageType' => strtolower($input['type'] ?? $input['messageType'] ?? 'text'),
                'timestamp' => $message['created_at'],
                'createdAt' => $message['created_at'],
                'isRead' => false
            ];

            ResponseHelper::success([
                'message' => $response,
                'content' => $response
            ], 'Message sent successfully', 201);
        } catch (Exception $e) {
            ResponseHelper::error('Failed to send message: ' . $e->getMessage(), 500);
        }
    }

    public function markConversationRead($conversationId) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->chatModel->markMessagesAsRead($conversationId, $currentUser['id']);
            ResponseHelper::success(null, 'Conversation marked as read');
        } catch (Exception $e) {
            ResponseHelper::error('Failed to mark conversation as read: ' . $e->getMessage(), 500);
        }
    }

    public function getOnlineUsers() {
        ResponseHelper::success([
            'userIds' => [],
            'content' => []
        ]);
    }

    public function getFriends() {
        ResponseHelper::success([]);
    }

    public function setReadMessages() {
        ResponseHelper::success(null, 'Messages marked as read');
    }
    
    public function getUnseenCount() {
        try {
            // Get current user
            $currentUserEmail = $GLOBALS['current_user_email'] ?? null;
            if (!$currentUserEmail) {
                ResponseHelper::error('User not authenticated', 401);
            }
            
            $currentUser = $this->userModel->findByEmail($currentUserEmail);
            if (!$currentUser) {
                ResponseHelper::error('User not found', 404);
            }
            
            $count = $this->chatModel->getUnseenMessageCount($currentUser['id']);
            ResponseHelper::success(['unseenCount' => $count]);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get unseen count: ' . $e->getMessage(), 500);
        }
    }

    private function getCurrentUser() {
        $currentUserEmail = $GLOBALS['current_user_email'] ?? null;
        if (!$currentUserEmail) {
            ResponseHelper::error('User not authenticated', 401);
        }

        $currentUser = $this->userModel->findByEmail($currentUserEmail);
        if (!$currentUser) {
            ResponseHelper::error('User not found', 404);
        }

        return $currentUser;
    }
}
