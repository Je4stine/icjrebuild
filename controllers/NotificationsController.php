<?php
class NotificationsController {
    private $notificationModel;
    private $userModel;

    public function __construct() {
        $this->notificationModel = new Notification();
        $this->userModel = new User();
    }

    public function getNotifications() {
        try {
            $currentUser = $this->getCurrentUser();
            $limit = min(max((int)($_GET['limit'] ?? 50), 1), 100);
            $offset = max((int)($_GET['offset'] ?? 0), 0);

            $notifications = $this->notificationModel->getForUser($currentUser['id'], $limit, $offset);
            $total = $this->notificationModel->countForUser($currentUser['id']);

            ResponseHelper::success([
                'notifications' => $notifications,
                'content' => $notifications,
                'totalElements' => $total
            ]);
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get notifications: ' . $e->getMessage(), 500);
        }
    }

    public function getUnreadCount() {
        try {
            $currentUser = $this->getCurrentUser();
            $count = $this->notificationModel->getUnreadCount($currentUser['id']);

            ResponseHelper::success([
                'count' => $count,
                'unreadCount' => $count
            ]);
        } catch (Exception $e) {
            ResponseHelper::error('Failed to get notification count: ' . $e->getMessage(), 500);
        }
    }

    public function markAsRead() {
        try {
            $currentUser = $this->getCurrentUser();
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $ids = $input['notificationIds'] ?? $input['ids'] ?? [];

            $this->notificationModel->markAsRead($currentUser['id'], $ids);
            ResponseHelper::success(['notificationIds' => $ids], 'Notifications marked as read');
        } catch (Exception $e) {
            ResponseHelper::error('Failed to mark notifications as read: ' . $e->getMessage(), 500);
        }
    }

    public function markAllAsRead() {
        try {
            $currentUser = $this->getCurrentUser();
            $this->notificationModel->markAllAsRead($currentUser['id']);
            ResponseHelper::success(null, 'All notifications marked as read');
        } catch (Exception $e) {
            ResponseHelper::error('Failed to mark notifications as read: ' . $e->getMessage(), 500);
        }
    }

    public function markOneAsRead($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->notificationModel->markAsRead($currentUser['id'], [$id]);
            ResponseHelper::success(['id' => $id, 'isRead' => true], 'Notification marked as read');
        } catch (Exception $e) {
            ResponseHelper::error('Failed to mark notification as read: ' . $e->getMessage(), 500);
        }
    }

    public function deleteNotifications() {
        try {
            $currentUser = $this->getCurrentUser();
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $ids = $input['notificationIds'] ?? $input['ids'] ?? [];

            $this->notificationModel->deleteForUser($currentUser['id'], $ids);
            ResponseHelper::success(['notificationIds' => $ids], 'Notifications deleted');
        } catch (Exception $e) {
            ResponseHelper::error('Failed to delete notifications: ' . $e->getMessage(), 500);
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
}
