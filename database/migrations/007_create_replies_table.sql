-- Migration: 007_create_replies_table.sql
-- Description: Create replies table for threaded discussions

CREATE TABLE IF NOT EXISTS replies (
    id CHAR(36) PRIMARY KEY,
    content TEXT NOT NULL,
    author VARCHAR(255) NOT NULL,
    conversation_id CHAR(36) NOT NULL,
    parent_id CHAR(36) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES replies(id) ON DELETE CASCADE,
    INDEX idx_replies_conversation_id (conversation_id),
    INDEX idx_replies_parent_id (parent_id),
    INDEX idx_replies_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
