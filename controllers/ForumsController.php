<?php
class ForumsController {
    private $forumModel;
    private $userModel;
    
    public function __construct() {
        $this->forumModel = new Forum();
        $this->userModel = new User();
    }
    
    public function createForum() {
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
            
            // Validate input
            if (empty($input['forumName']) || empty($input['topic']) || empty($input['description'])) {
                ResponseHelper::error('Forum name, topic, and description are required', 400);
            }
            
            $forum = $this->forumModel->create($input, $currentUser['id']);
            
            $response = [
                'id' => $forum['id'],
                'forumName' => $forum['forum_name'],
                'topic' => $forum['topic'],
                'description' => $forum['description'],
                'createdAt' => $forum['created_at'],
                'userRequest' => [
                    'email' => $currentUser['email'],
                    'firstName' => $currentUser['first_name'],
                    'lastName' => $currentUser['last_name']
                ]
            ];
            
            ResponseHelper::success($response, 'Forum created successfully', 201);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to create forum: ' . $e->getMessage(), 500);
        }
    }
    
    public function getAllForums() {
        try {
            $pageNo = intval($_GET['pageNo'] ?? 0);
            $pageSize = intval($_GET['pageSize'] ?? 10);
            
            $pageSize = min($pageSize, 100);
            $pageNo = max($pageNo, 0);
            
            $forums = $this->forumModel->getAllForums($pageNo, $pageSize);
            $total = $this->forumModel->getTotalCount();
            
            ResponseHelper::paginated($forums, $total, $pageNo, $pageSize);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get forums: ' . $e->getMessage(), 500);
        }
    }
    
    public function getById($id) {
        try {
            $forum = $this->forumModel->getById($id);
            
            if (!$forum) {
                ResponseHelper::error('Forum not found', 404);
            }

            http_response_code(200);
            echo json_encode($forum);
            exit;
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get forum: ' . $e->getMessage(), 500);
        }
    }
    
    public function joinForum($forumId) {
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
            
            // Check if forum exists
            $forum = $this->forumModel->getById($forumId);
            if (!$forum) {
                ResponseHelper::error('Forum not found', 404);
            }
            
            $this->forumModel->joinForum($forumId, $currentUser['id']);
            
            ResponseHelper::success(null, 'Successfully joined forum');
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'already a member') !== false) {
                ResponseHelper::error($e->getMessage(), 409);
            } else {
                ResponseHelper::error('Failed to join forum: ' . $e->getMessage(), 500);
            }
        }
    }
    
    public function getForumsByUser($email) {
        try {
            $user = $this->userModel->findByEmail($email);
            if (!$user) {
                ResponseHelper::error('User not found', 404);
            }
            
            $forums = $this->forumModel->getForumsByUser($user['id']);
            
            $response = [
                'id' => $user['id'],
                'firstName' => $user['first_name'],
                'lastName' => $user['last_name'],
                'email' => $user['email'],
                'forums' => $forums
            ];
            
            ResponseHelper::success($response);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get user forums: ' . $e->getMessage(), 500);
        }
    }
    
    public function getMembers($id) {
        try {
            $memberCount = $this->forumModel->getMemberCount($id);
            ResponseHelper::success(['memberCount' => $memberCount]);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get member count: ' . $e->getMessage(), 500);
        }
    }
    
    public function createComment() {
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
            
            if (empty($input['comment']) || empty($input['forumId'])) {
                ResponseHelper::error('Comment and forum ID are required', 400);
            }
            
            $comment = $this->forumModel->createComment($input, $currentUser['id'], $input['forumId']);
            
            $response = [
                'id' => $comment['id'],
                'comment' => $comment['comment'],
                'createdAt' => $comment['created_at'],
                'firstName' => $currentUser['first_name'],
                'lastName' => $currentUser['last_name']
            ];
            
            ResponseHelper::success($response, 'Comment created successfully', 201);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to create comment: ' . $e->getMessage(), 500);
        }
    }
    
    public function createDiscussion() {
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
            
            $forumId = $input['forumId'] ?? $input['forum_id'] ?? null;
            if (empty($input['author']) || empty($input['content']) || empty($forumId)) {
                ResponseHelper::error('Author, content, and forum ID are required', 400);
            }
            
            $discussion = $this->forumModel->createDiscussion($input, $currentUser['id'], $forumId);
            
            $response = [
                'id' => $discussion['id'],
                'author' => $discussion['author'],
                'content' => $discussion['content'],
                'createdAt' => $discussion['created_at'],
                'forumId' => $forumId
            ];
            
            ResponseHelper::success($response, 'Discussion created successfully', 201);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to create discussion: ' . $e->getMessage(), 500);
        }
    }
    
    public function getAllForumDiscussions($forumId) {
        try {
            $discussions = $this->forumModel->getAllDiscussions($forumId);
            http_response_code(200);
            echo json_encode($discussions);
            exit;
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get discussions: ' . $e->getMessage(), 500);
        }
    }
    
    public function createReply() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['content']) || empty($input['author']) || empty($input['conversationId'])) {
                ResponseHelper::error('Content, author, and conversation ID are required', 400);
            }
            
            $parentId = $input['parentId'] ?? null;
            $reply = $this->forumModel->createReply($input, $input['conversationId'], $parentId);
            
            $response = [
                'id' => $reply['id'],
                'content' => $reply['content'],
                'author' => $reply['author'],
                'createdAt' => $reply['created_at'],
                'conversationId' => $input['conversationId'],
                'parentId' => $parentId
            ];
            
            ResponseHelper::success($response, 'Reply created successfully', 201);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to create reply: ' . $e->getMessage(), 500);
        }
    }
    
    public function getRepliesForConversation($conversationId) {
        try {
            $replies = $this->forumModel->getRepliesForConversation($conversationId);
            ResponseHelper::success($replies);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get replies: ' . $e->getMessage(), 500);
        }
    }
    
    public function getNestedReplies($parentId) {
        try {
            $replies = $this->forumModel->getNestedReplies($parentId);
            ResponseHelper::success($replies);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get nested replies: ' . $e->getMessage(), 500);
        }
    }
}
