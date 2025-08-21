<?php
// Load configuration first
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . implode(', ', ALLOWED_ORIGINS));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

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
