<?php
// Simple autoloader for our classes
spl_autoload_register(function ($className) {
    $directories = [
        __DIR__ . '/../controllers/',
        __DIR__ . '/../models/',
        __DIR__ . '/../services/',
        __DIR__ . '/../config/',
        __DIR__ . '/../utils/',
        __DIR__ . '/../middleware/'
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
