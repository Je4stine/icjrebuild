# Database Migration Guide

This guide explains how to set up and run database migrations for the ICJ Kenya API.

## Prerequisites

1. **PHP 7.4+** with PDO MySQL extension
2. **MySQL 5.7+** or **MariaDB 10.2+**
3. **Command line access**

## Setup Database

### 1. Create Database

First, create the MySQL database:

```sql
CREATE DATABASE icjkenya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Or using command line:
```bash
mysql -u root -p -e "CREATE DATABASE icjkenya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 2. Configure Database Connection

Update the database configuration in `config/config.php`:

```php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'icjkenya');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 3. Test Database Connection

Test the connection by running:

```bash
php migrate.php status
```

## Running Migrations

### Linux/Mac Commands

```bash
# Make migration script executable
chmod +x migrate.php

# Run all pending migrations
php migrate.php run

# Check migration status
php migrate.php status

# Rollback last migration
php migrate.php rollback

# Rollback multiple migrations
php migrate.php rollback 3

# Fresh install (drops all tables and recreates)
php migrate.php fresh

# Show help
php migrate.php help
```

### Windows Commands

```cmd
REM Run migrations using batch file
migrate.bat run

REM Or directly with PHP
php migrate.php run

REM Check status
migrate.bat status

REM Rollback
migrate.bat rollback
```

## Migration Commands Explained

### `run`
Executes all pending migrations in order. Safe to run multiple times - only new migrations will be executed.

```bash
php migrate.php run
```

### `status`
Shows the status of all migrations:

```bash
php migrate.php status
```

Output example:
```
Migration Status:
--------------------------------------------------------------------------------
Migration                                          Status
--------------------------------------------------------------------------------
001_create_users_table.sql                        ✓ Executed
002_create_posts_table.sql                        ✓ Executed  
003_create_discussion_forums_table.sql            ✗ Pending
...
```

### `rollback`
Removes migration records (doesn't automatically drop tables). Use with caution in production.

```bash
# Rollback last migration
php migrate.php rollback

# Rollback multiple migrations
php migrate.php rollback 3
```

### `fresh`
**⚠️ DESTRUCTIVE**: Drops all tables and runs all migrations. Use only in development.

```bash
php migrate.php fresh
```

## Migration Files

Migration files are located in `database/migrations/` and follow this naming convention:
- `001_create_users_table.sql`
- `002_create_posts_table.sql`
- etc.

### Creating New Migrations

1. Create a new `.sql` file in `database/migrations/`
2. Use the next sequential number: `013_your_migration_name.sql`
3. Write your SQL statements
4. Run `php migrate.php run`

Example migration file:
```sql
-- Migration: 013_add_user_avatar.sql
-- Description: Add avatar column to users table

ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER email;
CREATE INDEX idx_users_avatar ON users(avatar);
```

## Alternative: Manual Schema Import

If you prefer to set up the database manually without migrations:

```bash
mysql -u your_username -p icjkenya < database/schema.sql
```

This will create all tables at once.

## Troubleshooting

### Connection Issues

1. **"Database connection failed"**
   - Check MySQL is running: `sudo service mysql status`
   - Verify credentials in `config/config.php`
   - Ensure database exists
   - Check firewall settings

2. **"Access denied"**
   - Verify username/password
   - Grant privileges: `GRANT ALL PRIVILEGES ON icjkenya.* TO 'username'@'localhost';`

3. **"Database does not exist"**
   - Create database first: `CREATE DATABASE icjkenya;`

### Migration Issues

1. **"Migration file not found"**
   - Check file exists in `database/migrations/`
   - Verify file permissions

2. **"Migration failed"**
   - Check SQL syntax in migration file
   - Look for foreign key constraint issues
   - Ensure tables are created in correct order

3. **"Table already exists"**
   - Use `IF NOT EXISTS` in CREATE statements
   - Or run `php migrate.php fresh` to start over

### MySQL Version Issues

1. **UUID() function not available**
   - Upgrade to MySQL 8.0+ or MariaDB 10.7+
   - Or replace UUID() with custom UUID generation in PHP

2. **JSON column type not supported**
   - Use TEXT column instead for older MySQL versions

## Production Deployment

### Before Deployment

1. **Backup existing database**
   ```bash
   mysqldump -u username -p icjkenya > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Test migrations on staging**
   ```bash
   php migrate.php status  # Check current state
   php migrate.php run     # Run new migrations
   ```

3. **Verify application works** with new schema

### Deployment Process

1. **Put application in maintenance mode**
2. **Backup database**
3. **Run migrations**
   ```bash
   php migrate.php run
   ```
4. **Test critical functionality**
5. **Remove maintenance mode**

### Rollback Plan

If issues occur:
1. **Restore from backup**
   ```bash
   mysql -u username -p icjkenya < backup_file.sql
   ```
2. **Deploy previous application version**

## Security Notes

- Never run `fresh` in production
- Always backup before running migrations in production
- Use separate database users for different environments
- Store database credentials securely
- Use SSL for database connections in production

## Environment-Specific Configurations

### Development
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'dev_user');
define('DB_PASS', 'dev_password');
```

### Production
```php
define('DB_HOST', 'prod-db-server');
define('DB_USER', 'prod_user');
define('DB_PASS', 'strong_production_password');
```

Use environment variables or separate config files for different environments.
