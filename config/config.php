<?php
// Application configuration
define('APP_NAME', 'ICJ Kenya API');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development'); // development, staging, production

// Database configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'icjkenya');
define('DB_USER', 'root');
define('DB_PASS', '');

// JWT configuration
define('JWT_SECRET', 'your-secret-key-here-change-this-in-production-use-strong-random-key');
define('JWT_EXPIRATION', 3600 * 24); // 24 hours

// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'je4stine@gmail.com');
define('SMTP_PASSWORD', 'tnizufprkbqcgvry');
define('FROM_EMAIL', 'je4stine@gmail.com');
define('FROM_NAME', 'ICJ Kenya');

// CORS configuration
define('ALLOWED_ORIGINS', [
    'https://icjkenya.netlify.app',
    'http://localhost:5173',
    'http://localhost:3000'
]);

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
