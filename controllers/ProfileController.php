<?php
class ProfileController {
    public function getSettings() {
        ResponseHelper::success([
            'emailNotifications' => true,
            'forumActivity' => true,
            'language' => 'en'
        ]);
    }

    public function updateSettings() {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        ResponseHelper::success($input, 'Settings updated successfully');
    }

    public function getAccessibilitySettings() {
        ResponseHelper::success([
            'largeText' => false,
            'highContrast' => false,
            'reducedMotion' => false
        ]);
    }

    public function updateProfile() {
        ResponseHelper::success(null, 'Profile updated successfully');
    }
}
