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
        $this->post('/api/v1/auth/login', 'AuthController', 'login');
        $this->post('/api/v1/auth/logout', 'AuthController', 'logout');
        $this->put('/api/v1/auth/users/reset-password', 'AuthController', 'resetPassword', ['auth']);
        $this->delete('/api/v1/auth/deleteAccount/{email}', 'AuthController', 'deleteAccount', ['auth']);
        $this->get('/api/v1/auth/me', 'AuthController', 'getCurrentUser', ['auth']);
        $this->post('/api/v1/auth/forgotPassword', 'AuthController', 'forgotPassword');
        
        // Posts routes
        $this->post('/api/v1/posts/createPost', 'PostsController', 'createPost', ['auth']);
        $this->get('/api/v1/posts/allPosts', 'PostsController', 'getAllPosts');
        $this->get('/api/v1/posts/{id}/image', 'PostsController', 'getImageById');
        $this->get('/api/v1/posts/{id}/pdf', 'PostsController', 'getPdfById');
        $this->get('/api/v1/posts/post/{id}', 'PostsController', 'getPostById');
        $this->delete('/api/v1/posts/deletePost/{id}', 'PostsController', 'deletePost', ['auth']);
        $this->get('/api/v1/posts/allPostsByUserId/{userId}', 'PostsController', 'getAllPostsByUserId');
        
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
        
        // Chat routes
        $this->get('/api/v1/chat/conversations', 'ChatController', 'getConversations', ['auth']);
        $this->post('/api/v1/chat/send', 'ChatController', 'sendMessage', ['auth']);
        $this->get('/api/v1/chat/messages/{conversationId}', 'ChatController', 'getMessages', ['auth']);
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
}
