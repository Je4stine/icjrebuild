<?php
class ProfileController {
    private $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    public function getSettings() {
        $currentUser = $this->getCurrentUser();
        $preferences = $this->userModel->getPreferences($currentUser['id']);

        ResponseHelper::success([
            'notificationSettings' => $preferences['notificationSettings'],
            'privacySettings' => $preferences['privacySettings'],
            'accessibilitySettings' => $preferences['accessibilitySettings'],
            'language' => $preferences['language']
        ]);
    }

    public function updateSettings() {
        $currentUser = $this->getCurrentUser();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $accessibilitySettings = $input['accessibilitySettings'] ?? null;

        if ($accessibilitySettings === null && (
            isset($input['fontSize']) ||
            isset($input['highContrast']) ||
            isset($input['reducedMotion']) ||
            isset($input['darkMode'])
        )) {
            $accessibilitySettings = $input;
        }

        $preferences = $this->userModel->upsertPreferences($currentUser['id'], [
            'notificationSettings' => $input['notificationSettings'] ?? null,
            'privacySettings' => $input['privacySettings'] ?? null,
            'accessibilitySettings' => $accessibilitySettings,
            'language' => $input['language'] ?? null
        ]);

        ResponseHelper::success($preferences, 'Settings updated successfully');
    }

    public function getAccessibilitySettings() {
        $currentUser = $this->getCurrentUser();
        $preferences = $this->userModel->getPreferences($currentUser['id']);

        ResponseHelper::success([
            'fontSize' => $preferences['accessibilitySettings']['fontSize'] ?? 'medium',
            'highContrast' => $preferences['accessibilitySettings']['highContrast'] ?? false,
            'reducedMotion' => $preferences['accessibilitySettings']['reducedMotion'] ?? false,
            'darkMode' => $preferences['accessibilitySettings']['darkMode'] ?? false
        ]);
    }

    public function updateProfile() {
        $currentUser = $this->getCurrentUser();

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $firstname = trim($input['firstname'] ?? '');
        $lastname = trim($input['lastname'] ?? '');

        if ($firstname === '' || $lastname === '') {
            ResponseHelper::error('First name and last name are required', 400);
        }

        $updatedUser = $this->userModel->updateProfile($currentUser['id'], [
            'firstname' => $firstname,
            'lastname' => $lastname
        ]);

        ResponseHelper::success([
            'user' => [
                'id' => (int)$updatedUser['id'],
                'firstname' => $updatedUser['first_name'],
                'lastname' => $updatedUser['last_name'],
                'email' => $updatedUser['email']
            ]
        ], 'Profile updated successfully');
    }

    public function resetPassword() {
        $currentUser = $this->getCurrentUser();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $oldPassword = trim($input['oldPassword'] ?? '');
        $newPassword = trim($input['newPassword'] ?? '');

        if ($oldPassword === '' || $newPassword === '') {
            ResponseHelper::error('Current password and new password are required', 400);
        }

        if (strlen($newPassword) < 8) {
            ResponseHelper::error('New password must be at least 8 characters long', 400);
        }

        if (!password_verify($oldPassword, $currentUser['password'])) {
            ResponseHelper::error('Current password is incorrect', 401);
        }

        $this->userModel->resetPassword($currentUser['id'], $newPassword);

        ResponseHelper::success(null, 'Password updated successfully');
    }

    public function deleteAccount() {
        $currentUser = $this->getCurrentUser();
        $this->userModel->deleteAccount($currentUser['id']);

        ResponseHelper::success(null, 'Account deleted successfully');
    }

    private function getCurrentUser() {
        $currentUserEmail = $GLOBALS['current_user_email'] ?? null;
        if (!$currentUserEmail) {
            ResponseHelper::error('Authorization token required', 401);
        }

        $currentUser = $this->userModel->findByEmail($currentUserEmail);
        if (!$currentUser) {
            ResponseHelper::error('User not found', 404);
        }

        return $currentUser;
    }
}
