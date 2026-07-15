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

    public function google() {
        $frontendRedirectUri = $this->resolveFrontendOAuthRedirectUri($_GET['redirect_uri'] ?? null);
        if (!$frontendRedirectUri) {
            $this->error('Invalid OAuth redirect URI', 400);
        }

        if (!GOOGLE_CLIENT_ID || !GOOGLE_CLIENT_SECRET) {
            $this->redirectToFrontendOAuthCallback($frontendRedirectUri, [
                'error' => 'Google OAuth is not configured on the server.'
            ]);
        }

        $googleRedirectUri = $this->resolveGoogleRedirectUri();
        if (!$googleRedirectUri) {
            $this->redirectToFrontendOAuthCallback($frontendRedirectUri, [
                'error' => 'Google OAuth redirect URI is not configured.'
            ]);
        }

        $state = base64_encode(json_encode([
            'redirect_uri' => $frontendRedirectUri
        ]));

        $query = http_build_query([
            'client_id' => GOOGLE_CLIENT_ID,
            'redirect_uri' => $googleRedirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'offline',
            'prompt' => 'select_account',
            'state' => $state
        ]);

        header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query, true, 302);
        exit;
    }

    public function googleCallback() {
        $frontendRedirectUri = $this->resolveFrontendOAuthRedirectUriFromState($_GET['state'] ?? null);
        $frontendRedirectUri = $frontendRedirectUri ?: $this->resolveFrontendOAuthRedirectUri($_GET['redirect_uri'] ?? null);

        if (isset($_GET['error'])) {
            $this->redirectToFrontendOAuthCallback($frontendRedirectUri, [
                'error' => urldecode($_GET['error'])
            ]);
        }

        $code = $_GET['code'] ?? '';
        if ($code === '') {
            $this->redirectToFrontendOAuthCallback($frontendRedirectUri, [
                'error' => 'Missing Google authorization code.'
            ]);
        }

        try {
            $googleRedirectUri = $this->resolveGoogleRedirectUri();
            $tokenData = $this->exchangeGoogleCode($code, $googleRedirectUri);
            $profile = $this->fetchGoogleProfile($tokenData['access_token'] ?? null);

            if (!$profile || empty($profile['email'])) {
                $this->redirectToFrontendOAuthCallback($frontendRedirectUri, [
                    'error' => 'Unable to verify Google account.'
                ]);
            }

            $user = $this->userModel->findOrCreateOAuthUser([
                'firstname' => $profile['given_name'] ?? $this->fallbackFirstName($profile['name'] ?? $profile['email']),
                'lastname' => $profile['family_name'] ?? $this->fallbackLastName($profile['name'] ?? $profile['email']),
                'email' => $profile['email']
            ]);

            $appToken = $this->jwtService->generateToken($user['email'], $user['id']);
            $this->redirectToFrontendOAuthCallback($frontendRedirectUri, [
                'token' => $appToken,
                'firstname' => $user['first_name'],
                'lastname' => $user['last_name'],
                'email' => $user['email'],
                'id' => $user['id']
            ]);
        } catch (Exception $e) {
            $this->redirectToFrontendOAuthCallback($frontendRedirectUri, [
                'error' => 'Google sign-in failed.'
            ]);
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
            $user = $this->userModel->findByEmail($input['email']);
            $this->userModel->resetPassword($user['id'], $input['newPassword']);
            
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

    private function resolveFrontendOAuthRedirectUri($candidate = null) {
        $candidate = is_string($candidate) ? trim($candidate) : '';
        if ($candidate === '') {
            return $this->defaultFrontendOAuthRedirectUri();
        }

        if ($this->isAllowedFrontendRedirectUri($candidate)) {
            return $candidate;
        }

        return null;
    }

    private function resolveFrontendOAuthRedirectUriFromState($state = null) {
        if (!$state) {
            return null;
        }

        $decoded = json_decode(base64_decode((string)$state), true);
        $redirectUri = $decoded['redirect_uri'] ?? null;
        return $this->resolveFrontendOAuthRedirectUri($redirectUri);
    }

    private function defaultFrontendOAuthRedirectUri() {
        if (!empty($_SERVER['HTTP_ORIGIN']) && $this->isAllowedFrontendRedirectUri($_SERVER['HTTP_ORIGIN'] . '/auth/oauth/callback')) {
            return rtrim($_SERVER['HTTP_ORIGIN'], '/') . '/auth/oauth/callback';
        }

        if (!empty(ALLOWED_ORIGINS)) {
            return rtrim(ALLOWED_ORIGINS[0], '/') . '/auth/oauth/callback';
        }

        return null;
    }

    private function isAllowedFrontendRedirectUri($uri) {
        $parts = parse_url($uri);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $path = $parts['path'] ?? '';
        $expectedSuffix = '/auth/oauth/callback';
        $hasSuffix = strlen($path) >= strlen($expectedSuffix) && substr($path, -strlen($expectedSuffix)) === $expectedSuffix;

        return in_array($origin, ALLOWED_ORIGINS, true) && $hasSuffix;
    }

    private function resolveGoogleRedirectUri() {
        if (!empty(GOOGLE_REDIRECT_URI)) {
            return GOOGLE_REDIRECT_URI;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($host === '') {
            return null;
        }

        return $scheme . '://' . $host . '/api/v1/auth/google/callback';
    }

    private function exchangeGoogleCode($code, $redirectUri) {
        $payload = http_build_query([
            'code' => $code,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ]);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Google token exchange failed: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($result, true);
        if ($status < 200 || $status >= 300 || !is_array($data) || empty($data['access_token'])) {
            throw new Exception('Google token exchange failed');
        }

        return $data;
    }

    private function fetchGoogleProfile($accessToken) {
        if (!$accessToken) {
            return null;
        }

        $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken
            ]
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Google profile fetch failed: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($result, true);
        if ($status < 200 || $status >= 300 || !is_array($data)) {
            throw new Exception('Google profile fetch failed');
        }

        return $data;
    }

    private function fallbackFirstName($nameOrEmail) {
        $base = trim((string)$nameOrEmail);
        if ($base === '') {
            return 'Google';
        }

        $parts = preg_split('/\s+/', $base);
        return $parts[0] ?: 'Google';
    }

    private function fallbackLastName($nameOrEmail) {
        $base = trim((string)$nameOrEmail);
        if ($base === '') {
            return 'User';
        }

        $parts = preg_split('/\s+/', $base);
        if (count($parts) > 1) {
            return $parts[count($parts) - 1];
        }

        return 'User';
    }

    private function redirectToFrontendOAuthCallback($frontendRedirectUri, array $params = []) {
        $uri = $frontendRedirectUri ?: $this->defaultFrontendOAuthRedirectUri();
        if (!$uri) {
            $this->error('Unable to resolve frontend OAuth redirect URI', 500);
        }

        $query = http_build_query($params);
        $separator = strpos($uri, '?') === false ? '?' : '&';
        header('Location: ' . $uri . ($query ? $separator . $query : ''), true, 302);
        exit;
    }
}
