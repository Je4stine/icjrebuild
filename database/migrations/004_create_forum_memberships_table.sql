-- Migration: 004_create_forum_memberships_table.sql
-- Description: Create forum memberships table

CREATE TABLE IF NOT EXISTS forum_memberships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    forum_id CHAR(36) NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (forum_id) REFERENCES discussion_forums(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_membership (forum_id, user_id),
    INDEX idx_memberships_user_id (user_id),
    INDEX idx_memberships_forum_id (forum_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
