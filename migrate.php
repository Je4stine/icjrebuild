#!/usr/bin/env php
<?php
/**
 * Migration Command Line Tool
 * 
 * Usage:
 *   php migrate.php run              - Run pending migrations
 *   php migrate.php rollback [steps] - Rollback migrations (default: 1 step)
 *   php migrate.php status           - Show migration status
 *   php migrate.php fresh            - Drop all tables and run fresh migrations
 *   php migrate.php help             - Show this help message
 */

// Load configuration and dependencies
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/autoload.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/database/Migration.php';

function showHelp() {
    echo "ICJ Kenya API - Database Migration Tool\n";
    echo "=======================================\n\n";
    echo "Usage: php migrate.php [command] [options]\n\n";
    echo "Commands:\n";
    echo "  run              Run all pending migrations\n";
    echo "  rollback [steps] Rollback migrations (default: 1 step)\n";
    echo "  status           Show migration status\n";
    echo "  fresh            Drop all tables and run fresh migrations\n";
    echo "  help             Show this help message\n\n";
    echo "Examples:\n";
    echo "  php migrate.php run\n";
    echo "  php migrate.php rollback\n";
    echo "  php migrate.php rollback 3\n";
    echo "  php migrate.php status\n";
    echo "  php migrate.php fresh\n\n";
}

function checkDatabaseConnection() {
    try {
        $db = Database::getInstance();
        echo "✓ Database connection successful\n";
        return true;
    } catch (Exception $e) {
        echo "✗ Database connection failed: " . $e->getMessage() . "\n";
        echo "\nPlease check your database configuration in config/config.php\n";
        echo "Make sure MySQL is running and the database exists.\n\n";
        echo "To create the database, run:\n";
        echo "mysql -u " . DB_USER . " -p -e \"CREATE DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\"\n\n";
        return false;
    }
}

// Get command line arguments
$command = $argv[1] ?? 'help';
$options = array_slice($argv, 2);

echo "ICJ Kenya API - Migration Tool\n";
echo "==============================\n\n";

// Check database connection first
if (!checkDatabaseConnection()) {
    exit(1);
}

try {
    $migration = new Migration();
    
    switch ($command) {
        case 'run':
            $migration->run();
            break;
            
        case 'rollback':
            $steps = isset($options[0]) ? (int)$options[0] : 1;
            if ($steps < 1) {
                echo "Error: Steps must be a positive integer\n";
                exit(1);
            }
            $migration->rollback($steps);
            break;
            
        case 'status':
            $migration->status();
            break;
            
        case 'fresh':
            echo "WARNING: This will drop all tables and data!\n";
            echo "Are you sure you want to continue? (yes/no): ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            fclose($handle);
            
            if (trim(strtolower($line)) === 'yes') {
                $migration->fresh();
            } else {
                echo "Operation cancelled.\n";
            }
            break;
            
        case 'help':
        default:
            showHelp();
            break;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nDone!\n";
