<?php
function app_load_env_file($path) {
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        $firstChar = substr($value, 0, 1);
        $lastChar = substr($value, -1);
        if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
            $value = substr($value, 1, -1);
        }

        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

app_load_env_file(__DIR__ . '/../.env');

function app_env($key, $default = null) {
    $value = getenv($key);

    if ($value === false && isset($_ENV[$key])) {
        $value = $_ENV[$key];
    }

    if ($value === false && isset($_SERVER[$key])) {
        $value = $_SERVER[$key];
    }

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function app_default_env() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocalHost = $host === '' || strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;

    return $isLocalHost ? 'development' : 'production';
}

function app_env_list($key, array $default = []) {
    $value = app_env($key);

    if ($value === null) {
        return $default;
    }

    return array_values(array_filter(array_map('trim', explode(',', $value))));
}

// Application configuration
define('APP_NAME', 'ICJ Kenya API');
define('APP_VERSION', '1.0.0');
define('APP_ENV', app_env('APP_ENV', app_default_env())); // development, staging, production

// Database configuration
define('DB_HOST', app_env('DB_HOST', 'localhost'));
define('DB_PORT', app_env('DB_PORT', '3306'));
define('DB_NAME', app_env('DB_NAME', APP_ENV === 'production' ? null : 'icjkenya'));
define('DB_USER', app_env('DB_USER', APP_ENV === 'production' ? null : 'root'));
define('DB_PASS', app_env('DB_PASS', APP_ENV === 'production' ? null : ''));

// JWT configuration
define('JWT_SECRET', app_env('JWT_SECRET', 'your-secret-key-here-change-this-in-production-use-strong-random-key'));
define('JWT_EXPIRATION', 3600 * 24); // 24 hours

// Google OAuth configuration
define('GOOGLE_CLIENT_ID', app_env('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', app_env('GOOGLE_CLIENT_SECRET', ''));
define('GOOGLE_REDIRECT_URI', app_env('GOOGLE_REDIRECT_URI', ''));

// Email configuration
define('SMTP_HOST', app_env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', app_env('SMTP_PORT', 587));
define('SMTP_USERNAME', app_env('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', app_env('SMTP_PASSWORD', ''));
define('FROM_EMAIL', app_env('FROM_EMAIL', SMTP_USERNAME));
define('FROM_NAME', app_env('FROM_NAME', 'ICJ Kenya'));

// CORS configuration
define('ALLOWED_ORIGINS', app_env_list('ALLOWED_ORIGINS', [
    'https://icjkenya.netlify.app',
    'https://test.bullione.africa',
    'http://localhost:5173',
    'http://localhost:3000'
]));

// File upload configuration
define('MAX_IMAGE_SIZE', 10 * 1024 * 1024); // 10MB
define('MAX_DOCUMENT_SIZE', 20 * 1024 * 1024); // 20MB
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// API configuration
define('API_PREFIX', '/api/v1');
define('DEFAULT_PAGE_SIZE', 10);
define('MAX_PAGE_SIZE', 100);

// Error reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Set default timezone
date_default_timezone_set('Africa/Nairobi');

// Security settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', APP_ENV === 'production' ? 1 : 0);
ini_set('session.use_only_cookies', 1);

// Create upload directory if it doesn't exist
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
