<?php
class AuthMiddleware {
    public function handle() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $this->unauthorized('Missing or invalid authorization header');
            return;
        }
        
        $token = $matches[1];
        
        try {
            $jwtService = new JWTService();
            $payload = $jwtService->verifyToken($token);
            
            // Store user info in global variable for use in controllers
            $GLOBALS['current_user_email'] = $payload['email'];
            
        } catch (Exception $e) {
            $this->unauthorized('Invalid token: ' . $e->getMessage());
        }
    }
    
    private function unauthorized($message) {
        http_response_code(401);
        echo json_encode(['error' => $message]);
        exit;
    }
}
