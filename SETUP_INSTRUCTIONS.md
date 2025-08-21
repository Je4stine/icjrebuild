# ICJ Kenya API - Setup Instructions

## Quick Start Guide

### 1. Prerequisites Check

**PHP Installation:**
```bash
php --version
# Should show PHP 7.4 or higher
```

**MySQL Installation:**
```bash
mysql --version
# Should show MySQL 5.7+ or MariaDB 10.2+
```

**Required PHP Extensions:**
```bash
php -m | grep -E "(pdo|pdo_mysql|gd|json|mbstring)"
```

### 2. Database Setup

**Create Database:**
```sql
CREATE DATABASE icjkenya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Command line:**
```bash
mysql -u root -p -e "CREATE DATABASE icjkenya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3. Configuration

**Update `config/config.php`:**
```php
// Database configuration
define('DB_HOST', 'localhost');     // Your MySQL host
define('DB_PORT', '3306');          // Your MySQL port
define('DB_NAME', 'icjkenya');      // Database name
define('DB_USER', 'your_username'); // Your MySQL username
define('DB_PASS', 'your_password'); // Your MySQL password

// JWT Secret (CHANGE THIS!)
define('JWT_SECRET', 'your-very-long-and-secure-random-secret-key-here');

// Email configuration (for password reset)
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

### 4. Database Migration

**Using Migration System (Recommended):**

**Linux/Mac:**
```bash
cd php-api
php migrate.php run
```

**Windows:**
```cmd
cd php-api
migrate.bat run
```

**Alternative - Direct Schema Import:**
```bash
mysql -u your_username -p icjkenya < database/schema.sql
```

### 5. File Permissions

**Linux/Mac:**
```bash
chmod 755 php-api/
chmod 777 php-api/uploads/  # if you created this folder
chmod +x php-api/migrate.php
```

**Windows:**
- Ensure web server has read/write access to the php-api directory

### 6. Web Server Configuration

**Apache (.htaccess already included):**
- Ensure mod_rewrite is enabled
- Point DocumentRoot to php-api folder

**Nginx:**
Add to your server block:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

### 7. Test Installation

**Test Database Connection:**
```bash
php migrate.php status
```

**Test API Endpoint:**
```bash
curl http://localhost/api/v1/auth/signup \
  -H "Content-Type: application/json" \
  -d '{"firstname":"Test","lastname":"User","email":"test@example.com","password":"password123"}'
```

## Environment-Specific Setup

### Development Environment

**XAMPP/WAMP Setup:**
1. Copy `php-api` folder to `htdocs` (XAMPP) or `www` (WAMP)
2. Access via `http://localhost/php-api/`
3. Update config with localhost database settings

**Local Development Server:**
```bash
cd php-api
php -S localhost:8000
```

### Production Environment

**Security Checklist:**
- [ ] Change JWT_SECRET to a strong random key
- [ ] Set APP_ENV to 'production' in config.php
- [ ] Use strong database passwords
- [ ] Enable HTTPS
- [ ] Configure proper file permissions
- [ ] Set up database backups
- [ ] Configure error logging

**Production Config Example:**
```php
define('APP_ENV', 'production');
define('JWT_SECRET', 'very-long-random-string-at-least-64-characters-long');
define('DB_HOST', 'production-db-server');
define('DB_USER', 'production_user');
define('DB_PASS', 'strong-production-password');
```

## Troubleshooting

### Common Issues

**1. "Database connection failed"**
- Check MySQL is running
- Verify credentials in config.php
- Ensure database exists
- Check firewall settings

**2. "Class not found" errors**
- Verify autoload.php is included
- Check file paths and case sensitivity
- Ensure all required files exist

**3. "Headers already sent" errors**
- Check for whitespace before <?php tags
- Ensure no output before JSON responses
- Verify file encoding (use UTF-8 without BOM)

**4. File upload issues**
- Check PHP upload limits in php.ini:
  ```ini
  upload_max_filesize = 20M
  post_max_size = 25M
  max_file_uploads = 20
  ```
- Verify upload directory permissions

**5. CORS issues**
- Update ALLOWED_ORIGINS in config.php
- Check browser developer tools for CORS errors

### Testing the API

**Create a test user:**
```bash
curl -X POST http://localhost/api/v1/auth/signup \
  -H "Content-Type: application/json" \
  -d '{
    "firstname": "John",
    "lastname": "Doe", 
    "email": "john@example.com",
    "password": "password123"
  }'
```

**Login:**
```bash
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

**Create a post (with token):**
```bash
curl -X POST http://localhost/api/v1/posts/createPost \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F 'postRequest={"postTitle":"Test Post","postContent":"This is a test post"}' \
  -F 'mediaFile=@/path/to/image.jpg'
```

## Migration Commands Reference

```bash
# Run all pending migrations
php migrate.php run

# Check migration status  
php migrate.php status

# Rollback last migration
php migrate.php rollback

# Fresh install (DESTRUCTIVE - drops all tables)
php migrate.php fresh

# Get help
php migrate.php help
```

## API Documentation

Once set up, you can access:
- API endpoints: `http://localhost/api/v1/`
- Test with tools like Postman or curl

## Support

For detailed migration information, see `MIGRATION_GUIDE.md`
For full API documentation, see `README.md`
