<?php
// Load configuration first
require_once __DIR__ . '/config/config.php';

set_exception_handler(function ($exception) {
    error_log((string) $exception);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }

    $message = APP_ENV === 'development' ? $exception->getMessage() : 'Internal server error';
    echo json_encode(['error' => $message]);
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ALLOWED_ORIGINS;

header('Content-Type: application/json');

if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config/autoload.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/router.php';

// Start session for auth
session_start();

// Create router instance and handle request
$router = new Router();
$router->handleRequest();
