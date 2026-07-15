-- Migration: 019_create_user_preferences_table.sql
-- Description: Store profile settings such as notifications, privacy, and language

CREATE TABLE IF NOT EXISTS user_preferences (
    user_id INT PRIMARY KEY,
    notification_settings LONGTEXT NULL,
    privacy_settings LONGTEXT NULL,
    accessibility_settings LONGTEXT NULL,
    language VARCHAR(50) NOT NULL DEFAULT 'english',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_preferences_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
