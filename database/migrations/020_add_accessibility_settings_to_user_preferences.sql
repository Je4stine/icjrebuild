-- Migration: 020_add_accessibility_settings_to_user_preferences.sql
-- Description: Add accessibility settings storage for theme and display preferences

SET @accessibility_column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_preferences'
      AND COLUMN_NAME = 'accessibility_settings'
);

SET @accessibility_sql := IF(
    @accessibility_column_exists = 0,
    'ALTER TABLE user_preferences ADD COLUMN accessibility_settings LONGTEXT NULL AFTER privacy_settings',
    'SELECT 1'
);

PREPARE accessibility_stmt FROM @accessibility_sql;
EXECUTE accessibility_stmt;
DEALLOCATE PREPARE accessibility_stmt;
