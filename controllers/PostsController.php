<?php
class PostsController {
    private $postModel;
    private $userModel;
    private $notificationModel;
    private $moderationService;
    
    public function __construct() {
        $this->postModel = new Post();
        $this->userModel = new User();
        $this->notificationModel = new Notification();
        $this->moderationService = new ContentModerationService();
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
            
            // Handle multipart form data. Some clients append postRequest as a JSON
            // Blob, which PHP exposes in $_FILES instead of $_POST.
            $postData = $this->getPostRequestData();
            
            if (empty($postData['postTitle']) || empty($postData['postContent'])) {
                ResponseHelper::error('Post title and content are required', 400);
            }

            $this->moderationService->assertAllowed([
                $postData['postTitle'],
                $postData['postContent']
            ], 'Post contains blocked language');

            $category = $postData['category'] ?? $postData['categorySlug'] ?? null;
            if (empty($category)) {
                ResponseHelper::error('Category is required', 400);
            }

            if (!$this->isAllowedCategory($category)) {
                ResponseHelper::error('Invalid category', 400);
            }
            
            // Prepare data for creation
            $data = [
                'postTitle' => $postData['postTitle'],
                'postContent' => $postData['postContent'],
                'userId' => $currentUser['id'],
                'category' => $category
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
                if (!$this->isAllowedDocumentFile($documentFile)) {
                    ResponseHelper::error('Invalid document file type. Allowed: PDF, DOC, DOCX', 400);
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
                'category' => $createdPost['category'] ?? null,
                'categories' => !empty($createdPost['category']) ? [$createdPost['category']] : [],
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
            $currentUserId = $this->getCurrentUserIdFromRequest();
            
            // Ensure reasonable limits
            $pageSize = min($pageSize, 100);
            $pageNo = max($pageNo, 0);
            
            $posts = $this->postModel->getAllPosts($pageNo, $pageSize, $currentUserId);
            $total = $this->postModel->getTotalCount();
            
            ResponseHelper::paginated($posts, $total, $pageNo, $pageSize);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get posts: ' . $e->getMessage(), 500);
        }
    }

    public function getAllPostsFlat() {
        try {
            $pageNo = intval($_GET['pageNo'] ?? $_GET['page'] ?? 0);
            $pageSize = intval($_GET['pageSize'] ?? $_GET['size'] ?? 10);
            $currentUserId = $this->getCurrentUserIdFromRequest();
            $pageSize = min($pageSize, 100);
            $pageNo = max($pageNo, 0);

            $posts = $this->postModel->getAllPosts($pageNo, $pageSize, $currentUserId);
            $total = $this->postModel->getTotalCount();

            ResponseHelper::paginated($posts, $total, $pageNo, $pageSize);
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get posts: ' . $e->getMessage(), 500);
        }
    }

    public function searchPosts() {
        try {
            $query = trim($_GET['q'] ?? $_GET['query'] ?? '');
            $limit = min(intval($_GET['limit'] ?? 20), 100);
            $offset = max(intval($_GET['offset'] ?? 0), 0);
            $currentUserId = $this->getCurrentUserIdFromRequest();

            if ($query === '') {
                http_response_code(200);
                echo json_encode([]);
                exit;
            }

            http_response_code(200);
            echo json_encode($this->postModel->search($query, $limit, $offset, $currentUserId));
            exit;
        } catch (Exception $e) {
            ResponseHelper::error('Failed to search posts: ' . $e->getMessage(), 500);
        }
    }

    public function getPostsByCategory($slug) {
        try {
            if (!$this->isAllowedCategory($slug)) {
                ResponseHelper::error('Invalid category', 400);
            }

            $pageNo = intval($_GET['pageNo'] ?? $_GET['page'] ?? 0);
            $pageSize = min(intval($_GET['pageSize'] ?? $_GET['size'] ?? 100), 100);
            $pageNo = max($pageNo, 0);
            $currentUserId = $this->getCurrentUserIdFromRequest();

            $posts = $this->postModel->getByCategory($slug, $pageNo, $pageSize, $currentUserId);
            $total = $this->postModel->getCategoryCount($slug);

            ResponseHelper::paginated($posts, $total, $pageNo, $pageSize);
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get category posts: ' . $e->getMessage(), 500);
        }
    }

    public function searchPostsByCategory($slug) {
        try {
            if (!$this->isAllowedCategory($slug)) {
                ResponseHelper::error('Invalid category', 400);
            }

            $query = trim($_GET['q'] ?? $_GET['query'] ?? '');
            $limit = min(intval($_GET['limit'] ?? 20), 100);
            $offset = max(intval($_GET['offset'] ?? 0), 0);
            $currentUserId = $this->getCurrentUserIdFromRequest();

            http_response_code(200);
            if ($query === '') {
                echo json_encode($this->postModel->getByCategory($slug, 0, $limit, $currentUserId));
                exit;
            }

            echo json_encode($this->postModel->searchByCategory($slug, $query, $limit, $offset, $currentUserId));
            exit;
        } catch (Exception $e) {
            ResponseHelper::error('Failed to search category posts: ' . $e->getMessage(), 500);
        }
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
                ResponseHelper::error('Document not found', 404);
            }
            
            header('Content-Type: ' . $this->detectDocumentMimeType($pdfData));
            header('Content-Length: ' . strlen($pdfData));
            echo $pdfData;
            exit;
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get document: ' . $e->getMessage(), 500);
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
            $currentUserId = $this->getCurrentUserIdFromRequest();
            $post = $this->postModel->getById($id, $currentUserId);
            
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
            $alreadyLiked = $this->postModel->hasLiked($id, $currentUser['id']);

            if ($alreadyLiked) {
                ResponseHelper::success([
                    'postId' => $id,
                    'liked' => true,
                    'isLiked' => true,
                    'alreadyLiked' => true,
                    'likeCount' => $this->postModel->getLikeCount($id)
                ], 'Post already liked');
            }

            $wasCreated = $this->postModel->like($id, $currentUser['id']);
            $post = $this->postModel->getById($id);

            if ($wasCreated > 0 && $post && (int)$post['userId'] !== (int)$currentUser['id']) {
                $this->notificationModel->create([
                    'userId' => $post['userId'],
                    'actorId' => $currentUser['id'],
                    'type' => 'like',
                    'title' => 'Post liked',
                    'message' => trim($currentUser['first_name'] . ' ' . $currentUser['last_name']) . ' liked your post "' . $post['postTitle'] . '"',
                    'relatedType' => 'post',
                    'relatedId' => $post['id']
                ]);
            }

            ResponseHelper::success([
                'postId' => $id,
                'liked' => true,
                'isLiked' => true,
                'alreadyLiked' => false,
                'likeCount' => $this->postModel->getLikeCount($id)
            ], 'Post liked');
        } catch (Exception $e) {
            ResponseHelper::error('Failed to like post: ' . $e->getMessage(), 500);
        }
    }

    public function unlikePost($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->postModel->unlike($id, $currentUser['id']);
            ResponseHelper::success([
                'postId' => $id,
                'liked' => false,
                'isLiked' => false,
                'alreadyLiked' => false,
                'likeCount' => $this->postModel->getLikeCount($id)
            ], 'Post unliked');
        } catch (Exception $e) {
            ResponseHelper::error('Failed to unlike post: ' . $e->getMessage(), 500);
        }
    }

    public function bookmarkPost($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->postModel->bookmark($id, $currentUser['id']);
            ResponseHelper::success(['postId' => $id, 'bookmarked' => true], 'Post bookmarked');
        } catch (Exception $e) {
            ResponseHelper::error('Failed to bookmark post: ' . $e->getMessage(), 500);
        }
    }

    public function unbookmarkPost($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->postModel->unbookmark($id, $currentUser['id']);
            ResponseHelper::success(['postId' => $id, 'bookmarked' => false], 'Bookmark removed');
        } catch (Exception $e) {
            ResponseHelper::error('Failed to remove bookmark: ' . $e->getMessage(), 500);
        }
    }

    public function getComments($id) {
        try {
            $post = $this->postModel->getById($id);
            if (!$post) {
                ResponseHelper::error('Post not found', 404);
            }

            ResponseHelper::success($this->postModel->getComments($id));
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get comments: ' . $e->getMessage(), 500);
        }
    }

    public function createComment($id) {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $content = trim($input['content'] ?? '');

            if ($content === '') {
                ResponseHelper::error('Comment content is required', 400);
            }

            $this->moderationService->assertAllowed([$content], 'Comment contains blocked language');

            $currentUserEmail = $GLOBALS['current_user_email'] ?? null;
            $currentUser = $currentUserEmail ? $this->userModel->findByEmail($currentUserEmail) : null;
            if (!$currentUser) {
                ResponseHelper::error('User not authenticated', 401);
            }

            $post = $this->postModel->getById($id);
            if (!$post) {
                ResponseHelper::error('Post not found', 404);
            }

            $comment = $this->postModel->createComment($id, $currentUser['id'], $content);
            if (!$comment) {
                ResponseHelper::error('Failed to save comment', 500);
            }

            if ((int)$post['userId'] !== (int)$currentUser['id']) {
                $this->notificationModel->create([
                    'userId' => $post['userId'],
                    'actorId' => $currentUser['id'],
                    'type' => 'comment',
                    'title' => 'New comment',
                    'message' => trim($currentUser['first_name'] . ' ' . $currentUser['last_name']) . ' commented on your post "' . $post['postTitle'] . '"',
                    'relatedType' => 'post',
                    'relatedId' => $post['id']
                ]);
            }

            ResponseHelper::success([
                'id' => $comment['id'],
                'content' => $comment['comment'],
                'author' => [
                    'firstName' => $comment['first_name'],
                    'lastName' => $comment['last_name']
                ],
                'createdAt' => $comment['created_at'],
                'likes' => 0
            ], 'Comment posted successfully', 201);
        } catch (Exception $e) {
            ResponseHelper::error('Failed to create comment: ' . $e->getMessage(), 500);
        }
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
            $currentUserId = $this->getCurrentUserIdFromRequest();
            $pageNo = intval($_GET['pageNo'] ?? 0);
            $pageSize = min(intval($_GET['pageSize'] ?? 10), 100);
            $pageNo = max($pageNo, 0);

            $filters = [
                'search' => trim($_GET['q'] ?? $_GET['query'] ?? ''),
                'documentFilter' => trim($_GET['documentFilter'] ?? 'all'),
                'sortBy' => trim($_GET['sortBy'] ?? 'newest')
            ];

            $posts = $this->postModel->getPostsByUserIdPaginated($userId, $pageNo, $pageSize, $currentUserId, $filters);
            $total = $this->postModel->getPostsByUserIdPaginatedCount($userId, $filters);

            ResponseHelper::paginated($posts, $total, $pageNo, $pageSize);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get user posts: ' . $e->getMessage(), 500);
        }
    }

    public function getBookmarkedPosts() {
        try {
            $currentUser = $this->getCurrentUser();
            $pageNo = intval($_GET['pageNo'] ?? 0);
            $pageSize = min(intval($_GET['pageSize'] ?? 10), 100);
            $pageNo = max($pageNo, 0);

            $filters = [
                'search' => trim($_GET['q'] ?? $_GET['query'] ?? ''),
                'category' => trim($_GET['category'] ?? ''),
                'sortBy' => trim($_GET['sortBy'] ?? 'recent')
            ];

            $posts = $this->postModel->getBookmarkedPosts($currentUser['id'], $pageNo, $pageSize, $currentUser['id'], $filters);
            $total = $this->postModel->getBookmarkedPostsCount($currentUser['id'], $filters);

            ResponseHelper::paginated($posts, $total, $pageNo, $pageSize);
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get bookmarked posts: ' . $e->getMessage(), 500);
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

    private function getCurrentUserIdFromRequest() {
        if (isset($GLOBALS['current_user_id']) && $GLOBALS['current_user_id'] !== null) {
            return (int)$GLOBALS['current_user_id'];
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        try {
            $jwtService = new JWTService();
            $payload = $jwtService->verifyToken($matches[1]);
            return isset($payload['user_id']) ? (int)$payload['user_id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function isAllowedCategory($category) {
        return in_array($category, [
            'human-rights',
            'electoral-law',
            'constitution',
            'african-systems',
            'civic-space',
            'democracy-governance',
            'judicial-independence',
            'technology-law'
        ], true);
    }

    private function getPostRequestData() {
        $rawPostRequest = $_POST['postRequest'] ?? null;

        if ($rawPostRequest === null && isset($_FILES['postRequest']) && $_FILES['postRequest']['error'] === UPLOAD_ERR_OK) {
            $rawPostRequest = file_get_contents($_FILES['postRequest']['tmp_name']);
        }

        if ($rawPostRequest === null || $rawPostRequest === '') {
            return [];
        }

        $postData = json_decode($rawPostRequest, true);
        if (!is_array($postData)) {
            ResponseHelper::error('Invalid postRequest JSON', 400);
        }

        return $postData;
    }

    private function isAllowedDocumentFile($file) {
        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'doc', 'docx'];

        if (!in_array($extension, $allowedExtensions)) {
            return false;
        }

        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/octet-stream',
            'application/zip'
        ];

        $mimeType = $file['type'] ?? '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectedMimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if ($detectedMimeType) {
                    $mimeType = $detectedMimeType;
                }
            }
        }

        return !$mimeType || in_array($mimeType, $allowedMimeTypes);
    }

    private function detectDocumentMimeType($documentData) {
        if (strncmp($documentData, '%PDF', 4) === 0) {
            return 'application/pdf';
        }

        if (strncmp($documentData, "PK\x03\x04", 4) === 0) {
            return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        }

        if (strncmp($documentData, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", 8) === 0) {
            return 'application/msword';
        }

        return 'application/octet-stream';
    }
}
