<?php
class AuthMiddleware {
    public function handle() {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (!is_array($headers)) {
            $headers = [];
        }

        $authHeader =
            $headers['Authorization'] ??
            $headers['authorization'] ??
            $_SERVER['HTTP_AUTHORIZATION'] ??
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ??
            null;

        if (!$authHeader && function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            if (is_array($apacheHeaders)) {
                $authHeader =
                    $apacheHeaders['Authorization'] ??
                    $apacheHeaders['authorization'] ??
                    $apacheHeaders['HTTP_AUTHORIZATION'] ??
                    $apacheHeaders['Http_Authorization'] ??
                    null;
            }
        }
        
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
            $GLOBALS['current_user_id'] = $payload['user_id'] ?? null;
            
        } catch (Exception $e) {
            $this->unauthorized('Invalid or expired token');
        }
    }
    
    private function unauthorized($message) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'data' => null
        ]);
        exit;
    }
}
