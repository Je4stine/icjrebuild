-- Migration: 006_create_conversations_table.sql
-- Description: Create conversations table for forum discussions

CREATE TABLE IF NOT EXISTS conversations (
    id CHAR(36) PRIMARY KEY,
    author VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    forum_id CHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (forum_id) REFERENCES discussion_forums(id) ON DELETE CASCADE,
    INDEX idx_conversations_forum_id (forum_id),
    INDEX idx_conversations_created_at (created_at),
    FULLTEXT idx_conversations_content (content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
