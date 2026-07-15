-- Migration: 015_add_category_to_posts_table.sql
-- Description: Add category slug to posts for category filtering

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'posts'
      AND COLUMN_NAME = 'category'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE posts ADD COLUMN category VARCHAR(100) NULL AFTER post_content',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'posts'
      AND INDEX_NAME = 'idx_posts_category'
);

SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE posts ADD INDEX idx_posts_category (category)',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
