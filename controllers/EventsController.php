<?php
class EventsController {
    private $db;
    private $events = [
        [
            'id' => 1,
            'title' => 'Constitutional Law Forum',
            'location' => 'Nairobi',
            'startDate' => '2026-08-15',
            'startTime' => '09:00',
            'description' => 'A public forum on constitutionalism, rights, and governance.',
            'isLiked' => false,
            'isBookmarked' => false,
            'likes' => 0
        ],
        [
            'id' => 2,
            'title' => 'Human Rights Workshop',
            'location' => 'Mombasa',
            'startDate' => '2026-09-05',
            'startTime' => '10:00',
            'description' => 'Practical training on human rights advocacy.',
            'isLiked' => false,
            'isBookmarked' => false,
            'likes' => 0
        ]
    ];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getEvents() {
        $currentUserId = $this->getCurrentUserIdFromRequest();
        $events = array_map(function ($event) use ($currentUserId) {
            return $this->decorateEvent($event, $currentUserId);
        }, $this->events);

        ResponseHelper::success([
            'events' => $events,
            'content' => $events
        ]);
    }

    public function getEventById($id) {
        foreach ($this->events as $event) {
            if ((string)$event['id'] === (string)$id) {
                ResponseHelper::success($this->decorateEvent($event, $this->getCurrentUserIdFromRequest()));
            }
        }

        ResponseHelper::error('Event not found', 404);
    }

    public function getFilters() {
        ResponseHelper::success([
            'locations' => ['Nairobi', 'Mombasa'],
            'categories' => ['Human Rights', 'Constitutional Law']
        ]);
    }

    public function search() {
        $query = strtolower(trim($_GET['q'] ?? ''));
        $currentUserId = $this->getCurrentUserIdFromRequest();
        $events = array_values(array_filter($this->events, function ($event) use ($query) {
            return $query === '' ||
                strpos(strtolower($event['title']), $query) !== false ||
                strpos(strtolower($event['description']), $query) !== false;
        }));

        $events = array_map(function ($event) use ($currentUserId) {
            return $this->decorateEvent($event, $currentUserId);
        }, $events);

        ResponseHelper::success(['events' => $events, 'content' => $events]);
    }

    public function like($id) {
        $currentUserId = $this->getCurrentUserIdFromRequest();
        if (!$currentUserId) {
            ResponseHelper::error('User not authenticated', 401);
        }

        $this->assertEventExists($id);
        $this->db->execute(
            "INSERT IGNORE INTO event_likes (user_id, event_id) VALUES (:user_id, :event_id)",
            [':user_id' => $currentUserId, ':event_id' => $id]
        );

        ResponseHelper::success($this->getEventLikeSummary($id, $currentUserId), 'Event liked');
    }

    public function unlike($id) {
        $currentUserId = $this->getCurrentUserIdFromRequest();
        if (!$currentUserId) {
            ResponseHelper::error('User not authenticated', 401);
        }

        $this->assertEventExists($id);
        $this->db->execute(
            "DELETE FROM event_likes WHERE user_id = :user_id AND event_id = :event_id",
            [':user_id' => $currentUserId, ':event_id' => $id]
        );

        ResponseHelper::success($this->getEventLikeSummary($id, $currentUserId), 'Event unliked');
    }

    public function bookmark($id) {
        ResponseHelper::success(['id' => $id, 'bookmarked' => true], 'Event bookmarked');
    }

    public function unbookmark($id) {
        ResponseHelper::success(['id' => $id, 'bookmarked' => false], 'Event bookmark removed');
    }

    public function register($id) {
        ResponseHelper::success(['id' => $id, 'registered' => true], 'Event registration successful');
    }

    private function getCurrentUserIdFromRequest() {
        if (isset($GLOBALS['current_user_id']) && $GLOBALS['current_user_id'] !== null) {
            return (int)$GLOBALS['current_user_id'];
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        try {
            $jwtService = new JWTService();
            $payload = $jwtService->verifyToken($matches[1]);
            return isset($payload['user_id']) ? (int)$payload['user_id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function decorateEvent($event, $currentUserId = null) {
        $likeCount = $this->getEventLikeCount($event['id']);
        return array_merge($event, [
            'likes' => $likeCount,
            'isLiked' => $this->isEventLikedByUser($event['id'], $currentUserId),
        ]);
    }

    private function getEventLikeCount($eventId) {
        $row = $this->db->fetch(
            "SELECT COUNT(*) as count FROM event_likes WHERE event_id = :event_id",
            [':event_id' => $eventId]
        );

        return (int)($row['count'] ?? 0);
    }

    private function isEventLikedByUser($eventId, $currentUserId = null) {
        if (!$currentUserId) {
            return false;
        }

        $row = $this->db->fetch(
            "SELECT COUNT(*) as count FROM event_likes WHERE event_id = :event_id AND user_id = :user_id",
            [':event_id' => $eventId, ':user_id' => $currentUserId]
        );

        return (int)($row['count'] ?? 0) > 0;
    }

    private function getEventLikeSummary($eventId, $currentUserId = null) {
        return [
            'id' => $eventId,
            'liked' => $this->isEventLikedByUser($eventId, $currentUserId),
            'likes' => $this->getEventLikeCount($eventId),
        ];
    }

    private function assertEventExists($id) {
        foreach ($this->events as $event) {
            if ((string)$event['id'] === (string)$id) {
                return;
            }
        }

        ResponseHelper::error('Event not found', 404);
    }
}
