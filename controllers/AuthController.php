<?php
class AuthController {
    private $userModel;
    private $jwtService;
    
    public function __construct() {
        $this->userModel = new User();
        $this->jwtService = new JWTService();
    }
    
    public function register() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        if (empty($input['firstname']) || empty($input['lastname']) || 
            empty($input['email']) || empty($input['password'])) {
            $this->error('All fields are required', 400);
        }
        
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email format', 400);
        }

        if (strlen($input['password']) < 6) {
            $this->error('Password must be at least 6 characters long', 400);
        }
        
        // Check if user already exists
        if ($this->userModel->existsByEmail($input['email'])) {
            $this->error('Email already exists', 409);
        }
        
        try {
            // Create user
            $user = $this->userModel->create($input);
            
            // Generate JWT token
            $token = $this->jwtService->generateToken($user['email'], $user['id']);
            
            // Return response
            $response = [
                'user' => $this->userModel->toAuthResponse($user),
                'token' => $token
            ];
            
            $this->authSuccess($user, $token, 'User registered successfully', 201);
            
        } catch (Exception $e) {
            $this->error('Unable to create user', 500);
        }
    }

    public function signin() {
        $this->login();
    }
    
    public function login() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        if (empty($input['email']) || empty($input['password'])) {
            $this->error('Email and password are required', 400);
        }

        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email format', 400);
        }
        
        try {
            // Verify credentials
            if (!$this->userModel->verifyPassword($input['email'], $input['password'])) {
                $this->error('Invalid credentials', 401);
            }
            
            // Get user details
            $user = $this->userModel->findByEmail($input['email']);
            
            // Generate JWT token
            $token = $this->jwtService->generateToken($user['email'], $user['id']);
            
            // Return response
            $response = [
                'user' => $this->userModel->toAuthResponse($user),
                'token' => $token
            ];
            
            $this->authSuccess($user, $token, 'Login successful');
            
        } catch (Exception $e) {
            $this->error('Invalid credentials', 401);
        }
    }
    
    public function resetPassword() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        if (empty($input['email']) || empty($input['oldPassword']) || empty($input['newPassword'])) {
            $this->error('Email, old password, and new password are required', 400);
        }

        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email format', 400);
        }

        if (strlen($input['newPassword']) < 6) {
            $this->error('New password must be at least 6 characters long', 400);
        }
        
        try {
            if (!$this->userModel->existsByEmail($input['email'])) {
                $this->error('User not found', 404);
            }

            // Verify old password
            if (!$this->userModel->verifyPassword($input['email'], $input['oldPassword'])) {
                $this->error('Current password is incorrect', 401);
            }
            
            // Update password
            $this->userModel->updatePassword($input['email'], $input['newPassword']);
            
            $this->success(null, 'Password updated successfully');
            
        } catch (Exception $e) {
            $this->error('Unable to update password', 500);
        }
    }
    
    public function deleteAccount($email) {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error('Invalid email format', 400);
            }

            // Check if user exists
            if (!$this->userModel->existsByEmail($email)) {
                $this->error('User not found', 404);
            }

            $user = $this->userModel->findByEmail($email);
            $currentUserId = $GLOBALS['current_user_id'] ?? null;

            if ($currentUserId !== null && (int)$currentUserId !== (int)$user['id']) {
                $this->error('You can only delete your own account', 403);
            }
            
            // Delete user
            $this->userModel->delete($email);
            
            $this->success(null, 'Account deleted successfully');
            
        } catch (Exception $e) {
            $this->error('Unable to delete account', 500);
        }
    }
    
    public function getCurrentUser() {
        try {
            $currentUserEmail = $GLOBALS['current_user_email'] ?? null;
            
            if (!$currentUserEmail) {
                $this->error('Authorization token required', 401);
            }
            
            $user = $this->userModel->findByEmail($currentUserEmail);
            
            if (!$user) {
                $this->error('User not found', 404);
            }
            
            $authUser = $this->userModel->toAuthResponse($user);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'User data retrieved successfully',
                'data' => [
                    'user' => $authUser
                ],
                'user' => $authUser,
                'id' => $authUser['id'],
                'firstname' => $authUser['firstname'],
                'lastname' => $authUser['lastname'],
                'firstName' => $authUser['firstname'],
                'lastName' => $authUser['lastname'],
                'email' => $authUser['email']
            ]);
            exit;
            
        } catch (Exception $e) {
            $this->error('User not found', 404);
        }
    }
    
    public function forgotPassword() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['email'])) {
            $this->error('Email is required', 400);
        }

        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email format', 400);
        }
        
        if (!$this->userModel->existsByEmail($input['email'])) {
            $this->success(null, 'If the email exists, a password reset link has been sent');
        }
        
        $this->success([
            'reset_token' => bin2hex(random_bytes(32))
        ], 'If the email exists, a password reset link has been sent');
    }
    
    public function logout() {
        // Since we're using stateless JWT, logout is handled client-side
        $this->success(null, 'Logged out successfully');
    }

    public function refresh() {
        $currentUserEmail = $GLOBALS['current_user_email'] ?? null;
        if (!$currentUserEmail) {
            $this->error('Authorization token required', 401);
        }

        $user = $this->userModel->findByEmail($currentUserEmail);
        if (!$user) {
            $this->error('User not found', 404);
        }

        $token = $this->jwtService->generateToken($user['email'], $user['id']);
        $this->authSuccess($user, $token, 'Token refreshed');
    }

    private function success($data = null, $message = 'Success', $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }

    private function authSuccess($user, $token, $message, $statusCode = 200) {
        $authUser = $this->userModel->toAuthResponse($user);
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => [
                'user' => $authUser,
                'token' => $token
            ],
            'user' => $authUser,
            'token' => $token,
            'id' => $authUser['id'],
            'firstname' => $authUser['firstname'],
            'lastname' => $authUser['lastname'],
            'email' => $authUser['email']
        ]);
        exit;
    }

    private function error($message = 'Error', $statusCode = 400, $data = null) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}
