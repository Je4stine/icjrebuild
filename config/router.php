<?php
class Router {
    private $routes = [];
    private $middleware = [];
    
    public function __construct() {
        $this->setupRoutes();
    }
    
    private function setupRoutes() {
        // Auth routes
        $this->post('/api/v1/auth/signup', 'AuthController', 'register');
        $this->post('/api/v1/auth/register', 'AuthController', 'register');
        $this->post('/api/v1/auth/signin', 'AuthController', 'signin');
        $this->post('/api/v1/auth/login', 'AuthController', 'login');
        $this->get('/api/v1/auth/google', 'AuthController', 'google');
        $this->get('/api/v1/auth/google/callback', 'AuthController', 'googleCallback');
        $this->post('/api/v1/auth/refresh', 'AuthController', 'refresh', ['auth']);
        $this->post('/api/v1/auth/logout', 'AuthController', 'logout');
        $this->post('/api/v1/auth/reset-password', 'AuthController', 'resetPassword');
        $this->put('/api/v1/auth/users/reset-password', 'AuthController', 'resetPassword', ['auth']);
        $this->delete('/api/v1/auth/deleteAccount/{email}', 'AuthController', 'deleteAccount', ['auth']);
        $this->get('/api/v1/auth/me', 'AuthController', 'getCurrentUser', ['auth']);
        $this->post('/api/v1/auth/forgotPassword', 'AuthController', 'forgotPassword');
        
        // Posts routes
        $this->get('/api/v1/posts/getAllPosts', 'PostsController', 'getAllPostsFlat');
        $this->get('/api/v1/posts/search', 'PostsController', 'searchPosts');
        $this->get('/api/v1/posts/category/{slug}', 'PostsController', 'getPostsByCategory');
        $this->get('/api/v1/posts/category/{slug}/search', 'PostsController', 'searchPostsByCategory');
        $this->post('/api/v1/posts/createPost', 'PostsController', 'createPost', ['auth']);
        $this->get('/api/v1/posts/allPosts', 'PostsController', 'getAllPosts');
        $this->get('/api/v1/posts/{id}/image', 'PostsController', 'getImageById');
        $this->get('/api/v1/posts/{id}/pdf', 'PostsController', 'getPdfById');
        $this->get('/api/v1/posts/{id}/file', 'PostsController', 'getFileById');
        $this->get('/api/v1/posts/{id}/download', 'PostsController', 'downloadPost');
        $this->post('/api/v1/posts/{id}/download-track', 'PostsController', 'trackDownload', ['auth']);
        $this->post('/api/v1/posts/{id}/view', 'PostsController', 'trackView', ['auth']);
        $this->get('/api/v1/posts/{id}/comments', 'PostsController', 'getComments');
        $this->post('/api/v1/posts/{id}/comments', 'PostsController', 'createComment', ['auth']);
        $this->post('/api/v1/posts/{id}/like', 'PostsController', 'likePost', ['auth']);
        $this->delete('/api/v1/posts/{id}/like', 'PostsController', 'unlikePost', ['auth']);
        $this->post('/api/v1/posts/{id}/unlike', 'PostsController', 'unlikePost', ['auth']);
        $this->post('/api/v1/posts/{id}/bookmark', 'PostsController', 'bookmarkPost', ['auth']);
        $this->delete('/api/v1/posts/{id}/bookmark', 'PostsController', 'unbookmarkPost', ['auth']);
        $this->post('/api/v1/posts/{id}/unbookmark', 'PostsController', 'unbookmarkPost', ['auth']);
        $this->get('/api/v1/posts/post/{id}', 'PostsController', 'getPostById');
        $this->get('/api/v1/posts/{id}', 'PostsController', 'getPostById');
        $this->delete('/api/v1/posts/{id}', 'PostsController', 'deletePost', ['auth']);
        $this->delete('/api/v1/posts/deletePost/{id}', 'PostsController', 'deletePost', ['auth']);
        $this->get('/api/v1/posts/allPostsByUserId/{userId}', 'PostsController', 'getAllPostsByUserId');
        $this->get('/api/v1/posts/bookmarkedPosts', 'PostsController', 'getBookmarkedPosts', ['auth']);
        
        // Forums routes
        $this->post('/api/v1/forum/createForum', 'ForumsController', 'createForum', ['auth']);
        $this->get('/api/v1/forum/getAllForums', 'ForumsController', 'getAllForums');
        $this->get('/api/v1/forum/{id}', 'ForumsController', 'getById');
        $this->post('/api/v1/forum/{forumId}/join', 'ForumsController', 'joinForum', ['auth']);
        $this->get('/api/v1/forum/{email}/forums', 'ForumsController', 'getForumsByUser', ['auth']);
        $this->get('/api/v1/forum/{id}/members', 'ForumsController', 'getMembers');
        $this->post('/api/v1/forum/comment', 'ForumsController', 'createComment', ['auth']);
        $this->post('/api/v1/forum/createDiscussion', 'ForumsController', 'createDiscussion', ['auth']);
        $this->get('/api/v1/forum/getAllForumDiscussions/{forum_id}', 'ForumsController', 'getAllForumDiscussions');
        $this->post('/api/v1/forum/createReply', 'ForumsController', 'createReply', ['auth']);
        $this->get('/api/v1/forum/replies/{conversationId}', 'ForumsController', 'getRepliesForConversation');
        $this->get('/api/v1/forum/nestedReplies/{parentId}', 'ForumsController', 'getNestedReplies');
        $this->post('/api/v1/forum/{targetType}/{targetId}/like', 'ForumsController', 'likeForumTarget', ['auth']);
        $this->delete('/api/v1/forum/{targetType}/{targetId}/like', 'ForumsController', 'unlikeForumTarget', ['auth']);
        $this->post('/api/v1/forum/{targetType}/{targetId}/unlike', 'ForumsController', 'unlikeForumTarget', ['auth']);
        
        // Chat routes
        $this->get('/api/v1/chat/conversations', 'ChatController', 'getConversations', ['auth']);
        $this->post('/api/v1/chat/conversations', 'ChatController', 'createConversation', ['auth']);
        $this->get('/api/v1/chat/conversations/{conversationId}/messages', 'ChatController', 'getMessages', ['auth']);
        $this->post('/api/v1/chat/conversations/{conversationId}/messages', 'ChatController', 'sendConversationMessage', ['auth']);
        $this->patch('/api/v1/chat/conversations/{conversationId}/mark-read', 'ChatController', 'markConversationRead', ['auth']);
        $this->get('/api/v1/chat/online-users', 'ChatController', 'getOnlineUsers', ['auth']);
        $this->post('/api/v1/chat/send', 'ChatController', 'sendMessage', ['auth']);
        $this->get('/api/v1/chat/messages/{conversationId}', 'ChatController', 'getMessages', ['auth']);
        $this->get('/api/conversation/friends', 'ChatController', 'getFriends', ['auth']);
        $this->get('/api/conversation/unseenMessages', 'ChatController', 'getUnseenCount', ['auth']);
        $this->get('/api/conversation/unseenMessages/{fromUserId}', 'ChatController', 'getUnseenCount', ['auth']);
        $this->put('/api/conversation/setReadMessages', 'ChatController', 'setReadMessages', ['auth']);

        // Users routes
        $this->get('/api/v1/users/search', 'UsersController', 'search', ['auth']);

        // Notifications routes
        $this->get('/api/v1/notifications', 'NotificationsController', 'getNotifications', ['auth']);
        $this->delete('/api/v1/notifications', 'NotificationsController', 'deleteNotifications', ['auth']);
        $this->get('/api/v1/notifications/unread-count', 'NotificationsController', 'getUnreadCount', ['auth']);
        $this->patch('/api/v1/notifications/mark-read', 'NotificationsController', 'markAsRead', ['auth']);
        $this->patch('/api/v1/notifications/mark-all-read', 'NotificationsController', 'markAllAsRead', ['auth']);
        $this->patch('/api/v1/notifications/{id}/read', 'NotificationsController', 'markOneAsRead', ['auth']);

        // Events routes
        $this->get('/api/v1/events', 'EventsController', 'getEvents');
        $this->get('/api/v1/events/filters', 'EventsController', 'getFilters');
        $this->get('/api/v1/events/search', 'EventsController', 'search');
        $this->post('/api/v1/events/{id}/like', 'EventsController', 'like', ['auth']);
        $this->post('/api/v1/events/{id}/unlike', 'EventsController', 'unlike', ['auth']);
        $this->post('/api/v1/events/{id}/bookmark', 'EventsController', 'bookmark', ['auth']);
        $this->post('/api/v1/events/{id}/unbookmark', 'EventsController', 'unbookmark', ['auth']);
        $this->post('/api/v1/events/{id}/register', 'EventsController', 'register', ['auth']);
        $this->get('/api/v1/events/{id}', 'EventsController', 'getEventById');
        $this->get('/api/events', 'EventsController', 'getEvents');
        $this->get('/api/events/filters', 'EventsController', 'getFilters');
        $this->get('/api/events/search', 'EventsController', 'search');
        $this->post('/api/events/{id}/like', 'EventsController', 'like', ['auth']);
        $this->post('/api/events/{id}/unlike', 'EventsController', 'unlike', ['auth']);
        $this->post('/api/events/{id}/bookmark', 'EventsController', 'bookmark', ['auth']);
        $this->post('/api/events/{id}/unbookmark', 'EventsController', 'unbookmark', ['auth']);
        $this->post('/api/events/{id}/register', 'EventsController', 'register', ['auth']);
        $this->get('/api/events/{id}', 'EventsController', 'getEventById');

        // Profile and support routes
        $this->get('/api/v1/profile/settings', 'ProfileController', 'getSettings', ['auth']);
        $this->put('/api/v1/profile/notification-settings', 'ProfileController', 'updateSettings', ['auth']);
        $this->put('/api/v1/profile/privacy-settings', 'ProfileController', 'updateSettings', ['auth']);
        $this->put('/api/v1/profile/language-settings', 'ProfileController', 'updateSettings', ['auth']);
        $this->get('/api/v1/profile/accessibility-settings', 'ProfileController', 'getAccessibilitySettings', ['auth']);
        $this->put('/api/v1/profile/accessibility-settings', 'ProfileController', 'updateSettings', ['auth']);
        $this->put('/api/v1/profile/update', 'ProfileController', 'updateProfile', ['auth']);
        $this->put('/api/v1/profile/reset-password', 'ProfileController', 'resetPassword', ['auth']);
        $this->delete('/api/v1/profile/delete-account', 'ProfileController', 'deleteAccount', ['auth']);
        $this->post('/api/v1/support/contact', 'SupportController', 'contact');
    }
    
    private function get($path, $controller, $method, $middleware = []) {
        $this->addRoute('GET', $path, $controller, $method, $middleware);
    }
    
    private function post($path, $controller, $method, $middleware = []) {
        $this->addRoute('POST', $path, $controller, $method, $middleware);
    }
    
    private function put($path, $controller, $method, $middleware = []) {
        $this->addRoute('PUT', $path, $controller, $method, $middleware);
    }

    private function patch($path, $controller, $method, $middleware = []) {
        $this->addRoute('PATCH', $path, $controller, $method, $middleware);
    }
    
    private function delete($path, $controller, $method, $middleware = []) {
        $this->addRoute('DELETE', $path, $controller, $method, $middleware);
    }
    
    private function addRoute($httpMethod, $path, $controller, $method, $middleware = []) {
        $this->routes[] = [
            'method' => $httpMethod,
            'path' => $path,
            'controller' => $controller,
            'action' => $method,
            'middleware' => $middleware
        ];
    }
    
    public function handleRequest() {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove base path if present (for subdirectory installations)
        $basePath = '/php-api';
        if (strpos($requestUri, $basePath) === 0) {
            $requestUri = substr($requestUri, strlen($basePath));
        }
        
        // Debug logging
        if (APP_ENV === 'development') {
            error_log("Router Debug: Method = $requestMethod, Original URI = " . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . ", Processed URI = $requestUri");
        }

        if ($requestMethod === 'GET' && ($requestUri === '' || $requestUri === '/')) {
            $this->sendJson(200, [
                'name' => APP_NAME,
                'version' => APP_VERSION,
                'status' => 'ok',
                'apiPrefix' => API_PREFIX,
                'endpoints' => [
                    'auth' => API_PREFIX . '/auth',
                    'posts' => API_PREFIX . '/posts',
                    'forum' => API_PREFIX . '/forum',
                    'chat' => API_PREFIX . '/chat'
                ]
            ]);
        }

        if ($requestMethod === 'GET' && $requestUri === API_PREFIX . '/health') {
            $this->sendJson(200, [
                'status' => 'ok',
                'service' => APP_NAME
            ]);
        }
        
        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod && $this->matchPath($route['path'], $requestUri)) {
                // Apply middleware
                foreach ($route['middleware'] as $middlewareName) {
                    $this->applyMiddleware($middlewareName);
                }
                
                // Extract parameters
                $params = $this->extractParams($route['path'], $requestUri);
                
                // Call controller
                $controllerName = $route['controller'];
                $actionName = $route['action'];
                
                if (class_exists($controllerName)) {
                    $controller = new $controllerName();
                    if (method_exists($controller, $actionName)) {
                        call_user_func_array([$controller, $actionName], $params);
                        return;
                    }
                }
                
                $this->sendError(500, 'Controller or method not found');
                return;
            }
        }
        
        $this->sendError(404, 'Route not found');
    }
    
    private function matchPath($routePath, $requestPath) {
        $routePattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $routePath);
        $routePattern = '#^' . $routePattern . '$#';
        return preg_match($routePattern, $requestPath);
    }
    
    private function extractParams($routePath, $requestPath) {
        $routePattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $routePath);
        $routePattern = '#^' . $routePattern . '$#';
        
        if (preg_match($routePattern, $requestPath, $matches)) {
            array_shift($matches); // Remove full match
            return $matches;
        }
        
        return [];
    }
    
    private function applyMiddleware($middlewareName) {
        switch ($middlewareName) {
            case 'auth':
                $authMiddleware = new AuthMiddleware();
                $authMiddleware->handle();
                break;
        }
    }
    
    private function sendError($code, $message) {
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit;
    }

    private function sendJson($code, $payload) {
        http_response_code($code);
        echo json_encode($payload);
        exit;
    }
}
