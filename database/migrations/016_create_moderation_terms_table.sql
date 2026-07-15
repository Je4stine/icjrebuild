-- Migration: 016_create_moderation_terms_table.sql
-- Description: Add local blocked terms for text moderation

CREATE TABLE IF NOT EXISTS moderation_terms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    term VARCHAR(255) NOT NULL UNIQUE,
    severity ENUM('review', 'block') NOT NULL DEFAULT 'block',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_moderation_terms_active (is_active),
    INDEX idx_moderation_terms_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO moderation_terms (term, severity) VALUES
('fuck', 'block'),
('shit', 'block'),
('bitch', 'block'),
('asshole', 'block'),
('bastard', 'block'),
('cunt', 'block'),
('dick', 'block'),
('pussy', 'block'),
('nigger', 'block'),
('faggot', 'block'),
('kike', 'block'),
('spic', 'block'),
('chink', 'block');
