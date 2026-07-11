<?php
class NotificationsController {
    public function getNotifications() {
        ResponseHelper::success([
            'notifications' => [],
            'content' => [],
            'totalElements' => 0
        ]);
    }

    public function getUnreadCount() {
        ResponseHelper::success([
            'count' => 0,
            'unreadCount' => 0
        ]);
    }

    public function markAsRead() {
        ResponseHelper::success(null, 'Notifications marked as read');
    }

    public function markAllAsRead() {
        ResponseHelper::success(null, 'All notifications marked as read');
    }

    public function markOneAsRead($id) {
        ResponseHelper::success(['id' => $id, 'isRead' => true], 'Notification marked as read');
    }

    public function deleteNotifications() {
        ResponseHelper::success(null, 'Notifications deleted');
    }
}
