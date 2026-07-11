<?php
class EventsController {
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

    public function getEvents() {
        ResponseHelper::success([
            'events' => $this->events,
            'content' => $this->events
        ]);
    }

    public function getEventById($id) {
        foreach ($this->events as $event) {
            if ((string)$event['id'] === (string)$id) {
                ResponseHelper::success($event);
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
        $events = array_values(array_filter($this->events, function ($event) use ($query) {
            return $query === '' ||
                strpos(strtolower($event['title']), $query) !== false ||
                strpos(strtolower($event['description']), $query) !== false;
        }));

        ResponseHelper::success(['events' => $events, 'content' => $events]);
    }

    public function like($id) {
        ResponseHelper::success(['id' => $id, 'liked' => true], 'Event liked');
    }

    public function unlike($id) {
        ResponseHelper::success(['id' => $id, 'liked' => false], 'Event unliked');
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
}
