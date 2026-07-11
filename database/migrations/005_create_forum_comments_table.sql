-- Migration: 005_create_forum_comments_table.sql
-- Description: Create forum comments table

CREATE TABLE IF NOT EXISTS forum_comments (
    id CHAR(36) PRIMARY KEY,
    comment TEXT NOT NULL,
    user_id INT NOT NULL,
    forum_id CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (forum_id) REFERENCES discussion_forums(id) ON DELETE CASCADE,
    INDEX idx_comments_forum_id (forum_id),
    INDEX idx_comments_user_id (user_id),
    INDEX idx_comments_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
