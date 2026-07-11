<?php
class PostsController {
    private $postModel;
    private $userModel;
    
    public function __construct() {
        $this->postModel = new Post();
        $this->userModel = new User();
    }
    
    public function createPost() {
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
            
            // Handle multipart form data
            $postData = json_decode($_POST['postRequest'] ?? '{}', true);
            
            if (empty($postData['postTitle']) || empty($postData['postContent'])) {
                ResponseHelper::error('Post title and content are required', 400);
            }
            
            // Prepare data for creation
            $data = [
                'postTitle' => $postData['postTitle'],
                'postContent' => $postData['postContent'],
                'userId' => $currentUser['id']
            ];
            
            // Handle file uploads
            $mediaFile = $_FILES['mediaFile'] ?? null;
            $documentFile = $_FILES['documentFile'] ?? null;
            
            // Validate file types
            if ($mediaFile && $mediaFile['error'] === UPLOAD_ERR_OK) {
                $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($mediaFile['type'], $allowedImageTypes)) {
                    ResponseHelper::error('Invalid image file type. Allowed: JPEG, PNG, GIF, WebP', 400);
                }
                
                // Check file size (max 10MB)
                if ($mediaFile['size'] > 10 * 1024 * 1024) {
                    ResponseHelper::error('Image file too large. Maximum size: 10MB', 400);
                }
            }
            
            if ($documentFile && $documentFile['error'] === UPLOAD_ERR_OK) {
                $allowedDocTypes = ['application/pdf'];
                if (!in_array($documentFile['type'], $allowedDocTypes)) {
                    ResponseHelper::error('Invalid document file type. Only PDF allowed', 400);
                }
                
                // Check file size (max 20MB)
                if ($documentFile['size'] > 20 * 1024 * 1024) {
                    ResponseHelper::error('Document file too large. Maximum size: 20MB', 400);
                }
            }
            
            // Create post
            $createdPost = $this->postModel->create($data, $mediaFile, $documentFile);
            
            $response = [
                'id' => $createdPost['id'],
                'postTitle' => $createdPost['post_title'],
                'postContent' => $createdPost['post_content'],
                'createdAt' => $createdPost['created_at'],
                'userRequest' => [
                    'email' => $currentUser['email'],
                    'firstName' => $currentUser['first_name'],
                    'lastName' => $currentUser['last_name']
                ]
            ];
            
            ResponseHelper::success($response, 'Post created successfully', 201);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to create post: ' . $e->getMessage(), 500);
        }
    }
    
    public function getAllPosts() {
        try {
            $pageNo = intval($_GET['pageNo'] ?? 0);
            $pageSize = intval($_GET['pageSize'] ?? 10);
            
            // Ensure reasonable limits
            $pageSize = min($pageSize, 100);
            $pageNo = max($pageNo, 0);
            
            $posts = $this->postModel->getAllPosts($pageNo, $pageSize);
            $total = $this->postModel->getTotalCount();
            
            ResponseHelper::paginated($posts, $total, $pageNo, $pageSize);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get posts: ' . $e->getMessage(), 500);
        }
    }

    public function getAllPostsFlat() {
        try {
            $pageNo = intval($_GET['pageNo'] ?? $_GET['page'] ?? 0);
            $pageSize = intval($_GET['pageSize'] ?? $_GET['size'] ?? 100);
            $pageSize = min($pageSize, 100);
            http_response_code(200);
            echo json_encode($this->postModel->getAllPosts($pageNo, $pageSize));
            exit;
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get posts: ' . $e->getMessage(), 500);
        }
    }

    public function searchPosts() {
        try {
            $query = trim($_GET['q'] ?? $_GET['query'] ?? '');
            $limit = min(intval($_GET['limit'] ?? 20), 100);
            $offset = max(intval($_GET['offset'] ?? 0), 0);

            if ($query === '') {
                http_response_code(200);
                echo json_encode([]);
                exit;
            }

            http_response_code(200);
            echo json_encode($this->postModel->search($query, $limit, $offset));
            exit;
        } catch (Exception $e) {
            ResponseHelper::error('Failed to search posts: ' . $e->getMessage(), 500);
        }
    }

    public function getPostsByCategory($slug) {
        try {
            $pageNo = intval($_GET['pageNo'] ?? $_GET['page'] ?? 0);
            $pageSize = min(intval($_GET['pageSize'] ?? $_GET['size'] ?? 100), 100);
            http_response_code(200);
            echo json_encode($this->postModel->getAllPosts($pageNo, $pageSize));
            exit;
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get category posts: ' . $e->getMessage(), 500);
        }
    }

    public function searchPostsByCategory($slug) {
        $this->searchPosts();
    }
    
    public function getImageById($id) {
        try {
            $imageData = $this->postModel->getImageById($id);
            
            if (!$imageData) {
                ResponseHelper::error('Image not found', 404);
            }
            
            header('Content-Type: image/jpeg');
            header('Content-Length: ' . strlen($imageData));
            echo $imageData;
            exit;
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get image: ' . $e->getMessage(), 500);
        }
    }
    
    public function getPdfById($id) {
        try {
            $pdfData = $this->postModel->getPdfById($id);
            
            if (!$pdfData) {
                ResponseHelper::error('PDF not found', 404);
            }
            
            header('Content-Type: application/pdf');
            header('Content-Length: ' . strlen($pdfData));
            echo $pdfData;
            exit;
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get PDF: ' . $e->getMessage(), 500);
        }
    }

    public function getFileById($id) {
        $this->getPdfById($id);
    }

    public function downloadPost($id) {
        $this->getPdfById($id);
    }
    
    public function getPostById($id) {
        try {
            $post = $this->postModel->getById($id);
            
            if (!$post) {
                ResponseHelper::error('Post not found', 404);
            }
            
            ResponseHelper::success($post);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get post: ' . $e->getMessage(), 500);
        }
    }

    public function trackView($id) {
        ResponseHelper::success(['id' => $id, 'views' => 0], 'View tracked');
    }

    public function trackDownload($id) {
        ResponseHelper::success(['id' => $id, 'downloads' => 0], 'Download tracked');
    }

    public function likePost($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->postModel->like($id, $currentUser['id']);
            ResponseHelper::success(['postId' => $id, 'liked' => true], 'Post liked');
        } catch (Exception $e) {
            ResponseHelper::error('Failed to like post: ' . $e->getMessage(), 500);
        }
    }

    public function unlikePost($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->postModel->unlike($id, $currentUser['id']);
            ResponseHelper::success(['postId' => $id, 'liked' => false], 'Post unliked');
        } catch (Exception $e) {
            ResponseHelper::error('Failed to unlike post: ' . $e->getMessage(), 500);
        }
    }

    public function bookmarkPost($id) {
        ResponseHelper::success(['postId' => $id, 'bookmarked' => true], 'Post bookmarked');
    }

    public function unbookmarkPost($id) {
        ResponseHelper::success(['postId' => $id, 'bookmarked' => false], 'Bookmark removed');
    }

    public function getComments($id) {
        ResponseHelper::success([]);
    }

    public function createComment($id) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $currentUserEmail = $GLOBALS['current_user_email'] ?? null;
        $currentUser = $currentUserEmail ? $this->userModel->findByEmail($currentUserEmail) : null;

        ResponseHelper::success([
            'id' => uniqid('comment_', true),
            'content' => $input['content'] ?? '',
            'author' => [
                'firstName' => $currentUser['first_name'] ?? 'User',
                'lastName' => $currentUser['last_name'] ?? ''
            ],
            'createdAt' => date('c'),
            'likes' => 0
        ], 'Comment posted successfully', 201);
    }
    
    public function deletePost($id) {
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
            
            // Check if post exists and user owns it
            $post = $this->postModel->getById($id);
            if (!$post) {
                ResponseHelper::error('Post not found', 404);
            }
            
            if ($post['user_id'] != $currentUser['id']) {
                ResponseHelper::error('Unauthorized to delete this post', 403);
            }
            
            // Delete post
            $this->postModel->delete($id);
            
            ResponseHelper::success(null, 'Post deleted successfully');
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to delete post: ' . $e->getMessage(), 500);
        }
    }
    
    public function getAllPostsByUserId($userId) {
        try {
            $posts = $this->postModel->getPostsByUserId($userId);
            ResponseHelper::success($posts);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get user posts: ' . $e->getMessage(), 500);
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
