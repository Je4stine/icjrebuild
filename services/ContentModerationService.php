<?php
class ContentModerationService {
    private $db;
    private $fallbackTerms = [
        'fuck',
        'shit',
        'bitch',
        'asshole',
        'bastard',
        'cunt',
        'dick',
        'pussy',
        'nigger',
        'faggot',
        'kike',
        'spic',
        'chink'
    ];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function checkFields($fields) {
        $text = $this->normalizeText(implode(' ', array_filter(array_map(function ($value) {
            return is_scalar($value) ? (string)$value : '';
        }, $fields))));

        if ($text === '') {
            return [
                'allowed' => true,
                'matchedTerms' => [],
                'severity' => null
            ];
        }

        $matchedTerms = [];
        $severity = null;

        foreach ($this->getTerms() as $term) {
            $normalizedTerm = $this->normalizeText($term['term']);
            if ($normalizedTerm === '') {
                continue;
            }

            if ($this->containsTerm($text, $normalizedTerm)) {
                $matchedTerms[] = $term['term'];
                $severity = $this->highestSeverity($severity, $term['severity'] ?? 'block');
            }
        }

        return [
            'allowed' => empty($matchedTerms),
            'matchedTerms' => array_values(array_unique($matchedTerms)),
            'severity' => $severity
        ];
    }

    public function assertAllowed($fields, $message = 'Content contains blocked language') {
        $result = $this->checkFields($fields);
        if (!$result['allowed']) {
            ResponseHelper::error($message, 400, [
                'moderation' => [
                    'provider' => 'local',
                    'severity' => $result['severity'],
                    'matchedTerms' => $result['matchedTerms']
                ]
            ]);
        }

        return $result;
    }

    private function getTerms() {
        try {
            $terms = $this->db->fetchAll(
                "SELECT term, severity FROM moderation_terms WHERE is_active = 1 ORDER BY term ASC"
            );

            if (!empty($terms)) {
                return $terms;
            }
        } catch (Exception $e) {
            error_log('Moderation terms lookup failed: ' . $e->getMessage());
        }

        return array_map(function ($term) {
            return ['term' => $term, 'severity' => 'block'];
        }, $this->fallbackTerms);
    }

    private function normalizeText($text) {
        $text = strtolower($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
        $text = preg_replace('/(.)\1{2,}/', '$1$1', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function containsTerm($text, $term) {
        return preg_match('/(^|\s)' . preg_quote($term, '/') . '(\s|$)/', $text) === 1;
    }

    private function highestSeverity($current, $next) {
        $rank = ['allow' => 0, 'review' => 1, 'block' => 2];
        $currentRank = $rank[$current] ?? -1;
        $nextRank = $rank[$next] ?? 2;
        return $nextRank > $currentRank ? $next : $current;
    }
}
