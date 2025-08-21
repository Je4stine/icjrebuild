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
            ResponseHelper::success($conversations);
            
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
            
            ResponseHelper::success($messages);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get messages: ' . $e->getMessage(), 500);
        }
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
}
