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
            ResponseHelper::error('All fields are required', 400);
        }
        
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            ResponseHelper::error('Invalid email format', 400);
        }
        
        // Check if user already exists
        if ($this->userModel->existsByEmail($input['email'])) {
            ResponseHelper::error('Email is already in use', 400);
        }
        
        try {
            // Create user
            $user = $this->userModel->create($input);
            
            // Generate JWT token
            $token = $this->jwtService->generateToken($user['email']);
            
            // Return response
            $response = [
                'firstName' => $user['first_name'],
                'lastName' => $user['last_name'],
                'email' => $user['email'],
                'token' => $token,
                'id' => $user['id']
            ];
            
            ResponseHelper::success($response, 'User created successfully', 201);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to create user: ' . $e->getMessage(), 500);
        }
    }
    
    public function login() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        if (empty($input['email']) || empty($input['password'])) {
            ResponseHelper::error('Email and password are required', 400);
        }
        
        try {
            // Verify credentials
            if (!$this->userModel->verifyPassword($input['email'], $input['password'])) {
                ResponseHelper::error('Invalid email or password', 401);
            }
            
            // Get user details
            $user = $this->userModel->findByEmail($input['email']);
            
            // Generate JWT token
            $token = $this->jwtService->generateToken($user['email']);
            
            // Return response
            $response = [
                'id' => $user['id'],
                'firstName' => $user['first_name'],
                'lastName' => $user['last_name'],
                'email' => $user['email'],
                'token' => $token
            ];
            
            ResponseHelper::success($response, 'Login successful');
            
        } catch (Exception $e) {
            ResponseHelper::error('Login failed: ' . $e->getMessage(), 500);
        }
    }
    
    public function resetPassword() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        if (empty($input['email']) || empty($input['oldPassword']) || empty($input['newPassword'])) {
            ResponseHelper::error('Email, old password, and new password are required', 400);
        }
        
        try {
            // Verify old password
            if (!$this->userModel->verifyPassword($input['email'], $input['oldPassword'])) {
                ResponseHelper::error('Invalid current password', 401);
            }
            
            // Update password
            $this->userModel->updatePassword($input['email'], $input['newPassword']);
            
            ResponseHelper::success(null, 'Password reset successfully');
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to reset password: ' . $e->getMessage(), 500);
        }
    }
    
    public function deleteAccount($email) {
        try {
            // Check if user exists
            if (!$this->userModel->existsByEmail($email)) {
                ResponseHelper::error('User not found', 404);
            }
            
            // Delete user
            $this->userModel->delete($email);
            
            ResponseHelper::success(null, 'Account deleted successfully');
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to delete account: ' . $e->getMessage(), 500);
        }
    }
    
    public function getCurrentUser() {
        try {
            $currentUserEmail = $GLOBALS['current_user_email'] ?? null;
            
            if (!$currentUserEmail) {
                ResponseHelper::error('User not authenticated', 401);
            }
            
            $user = $this->userModel->findByEmail($currentUserEmail);
            
            if (!$user) {
                ResponseHelper::error('User not found', 404);
            }
            
            // Remove password from response
            unset($user['password']);
            
            ResponseHelper::success($user);
            
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get current user: ' . $e->getMessage(), 500);
        }
    }
    
    public function forgotPassword() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['email'])) {
            ResponseHelper::error('Email is required', 400);
        }
        
        if (!$this->userModel->existsByEmail($input['email'])) {
            ResponseHelper::error('The email provided does not exist', 404);
        }
        
        // TODO: Implement email service for password reset
        ResponseHelper::success(null, 'Password reset instructions sent to your email');
    }
    
    public function logout() {
        // Since we're using stateless JWT, logout is handled client-side
        ResponseHelper::success(null, 'Logged out successfully');
    }
}
