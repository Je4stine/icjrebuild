-- Migration: 014_create_forum_likes_table.sql
-- Description: Create likes table for forum conversations and replies

CREATE TABLE IF NOT EXISTS forum_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    target_type ENUM('conversation', 'reply') NOT NULL,
    target_id CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_forum_like (user_id, target_type, target_id),
    INDEX idx_forum_likes_target (target_type, target_id),
    INDEX idx_forum_likes_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
