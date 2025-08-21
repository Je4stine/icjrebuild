<?php
class Migration {
    private $db;
    private $migrationsPath;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->migrationsPath = __DIR__ . '/migrations/';
    }
    
    public function run() {
        echo "Starting migrations...\n";
        
        // Create migrations table first
        $this->createMigrationsTable();
        
        // Get all migration files
        $migrationFiles = $this->getMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();
        
        $newMigrations = array_diff($migrationFiles, $executedMigrations);
        
        if (empty($newMigrations)) {
            echo "No new migrations to run.\n";
            return;
        }
        
        echo "Found " . count($newMigrations) . " new migration(s).\n";
        
        foreach ($newMigrations as $migration) {
            $this->executeMigration($migration);
        }
        
        echo "All migrations completed successfully!\n";
    }
    
    public function rollback($steps = 1) {
        echo "Rolling back $steps migration(s)...\n";
        
        $executedMigrations = $this->getExecutedMigrations();
        $migrationsToRollback = array_slice(array_reverse($executedMigrations), 0, $steps);
        
        if (empty($migrationsToRollback)) {
            echo "No migrations to rollback.\n";
            return;
        }
        
        foreach ($migrationsToRollback as $migration) {
            $this->rollbackMigration($migration);
        }
        
        echo "Rollback completed!\n";
    }
    
    public function status() {
        $migrationFiles = $this->getMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();
        
        echo "Migration Status:\n";
        echo str_repeat("-", 80) . "\n";
        printf("%-50s %s\n", "Migration", "Status");
        echo str_repeat("-", 80) . "\n";
        
        foreach ($migrationFiles as $migration) {
            $status = in_array($migration, $executedMigrations) ? "✓ Executed" : "✗ Pending";
            printf("%-50s %s\n", $migration, $status);
        }
        
        echo str_repeat("-", 80) . "\n";
        echo "Total: " . count($migrationFiles) . " migrations\n";
        echo "Executed: " . count($executedMigrations) . " migrations\n";
        echo "Pending: " . (count($migrationFiles) - count($executedMigrations)) . " migrations\n";
    }
    
    public function fresh() {
        echo "Dropping all tables and running fresh migrations...\n";
        
        // Drop all tables
        $this->dropAllTables();
        
        // Run all migrations
        $this->run();
        
        echo "Fresh migration completed!\n";
    }
    
    private function createMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_migrations_migration (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->execute($sql);
    }
    
    private function getMigrationFiles() {
        $files = glob($this->migrationsPath . '*.sql');
        $migrations = [];
        
        foreach ($files as $file) {
            $migrations[] = basename($file);
        }
        
        sort($migrations);
        return $migrations;
    }
    
    private function getExecutedMigrations() {
        try {
            $sql = "SELECT migration FROM migrations ORDER BY migration";
            $result = $this->db->fetchAll($sql);
            return array_column($result, 'migration');
        } catch (Exception $e) {
            // Migration table doesn't exist yet
            return [];
        }
    }
    
    private function executeMigration($migration) {
        echo "Executing migration: $migration\n";
        
        $filePath = $this->migrationsPath . $migration;
        
        if (!file_exists($filePath)) {
            throw new Exception("Migration file not found: $filePath");
        }
        
        $sql = file_get_contents($filePath);
        
        try {
            // Remove comments and split by semicolon
            $statements = $this->splitSqlStatements($sql);
            
            // Execute each statement directly (DDL statements in MySQL auto-commit)
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $this->db->execute($statement);
                }
            }
            
            // Record migration
            $this->db->execute(
                "INSERT INTO migrations (migration) VALUES (?)",
                [$migration]
            );
            
            echo "✓ Migration $migration executed successfully\n";
            
        } catch (Exception $e) {
            throw new Exception("Migration $migration failed: " . $e->getMessage());
        }
    }
    
    private function rollbackMigration($migration) {
        echo "Rolling back migration: $migration\n";
        
        // For simplicity, we'll just remove the migration record
        // In a more sophisticated system, you'd have separate rollback SQL files
        try {
            $this->db->execute(
                "DELETE FROM migrations WHERE migration = ?",
                [$migration]
            );
            echo "✓ Migration $migration rolled back\n";
        } catch (Exception $e) {
            throw new Exception("Rollback of $migration failed: " . $e->getMessage());
        }
    }
    
    private function dropAllTables() {
        // Get all tables
        $tables = $this->db->fetchAll("SHOW TABLES");
        $tableColumn = "Tables_in_" . DB_NAME;
        
        if (empty($tables)) {
            echo "No tables to drop.\n";
            return;
        }
        
        // Disable foreign key checks
        $this->db->execute("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($tables as $table) {
            $tableName = $table[$tableColumn];
            echo "Dropping table: $tableName\n";
            $this->db->execute("DROP TABLE IF EXISTS `$tableName`");
        }
        
        // Re-enable foreign key checks
        $this->db->execute("SET FOREIGN_KEY_CHECKS = 1");
    }
    
    private function splitSqlStatements($sql) {
        // Remove SQL comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Split by semicolon
        $statements = explode(';', $sql);
        
        return array_filter($statements, function($statement) {
            return !empty(trim($statement));
        });
    }
}
