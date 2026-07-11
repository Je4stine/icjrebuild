-- Migration: 003_create_discussion_forums_table.sql
-- Description: Create discussion forums table

CREATE TABLE IF NOT EXISTS discussion_forums (
    id CHAR(36) PRIMARY KEY,
    forum_name VARCHAR(255) NOT NULL,
    topic VARCHAR(255) NOT NULL,
    description TEXT,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_forums_user_id (user_id),
    INDEX idx_forums_created_at (created_at),
    FULLTEXT idx_forums_search (forum_name, topic, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
