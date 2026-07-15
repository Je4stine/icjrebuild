# ICJ Kenya API - Vanilla PHP Version

This is a vanilla PHP conversion of the original Java Spring Boot API for ICJ Kenya platform.

## Features

- **User Authentication**: JWT-based authentication with signup, login, password reset
- **Posts System**: Create, read, update, delete posts with media and document uploads
- **Forums System**: Discussion forums with topics, comments, and threaded replies
- **Chat System**: Real-time messaging between users
- **File Handling**: Image and PDF upload with validation and compression
- **Email Service**: Password reset and welcome emails
- **Database**: PostgreSQL with proper indexing and relationships

## Requirements

- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.2+
- Apache/Nginx web server with mod_rewrite
- GD extension for image processing
- PDO MySQL extension

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd php-api
   ```

2. **Configure Database**
   - Copy `.env.example` to `.env`
   - Fill in your database password in `DB_PASS`
   - Run migrations: `php migrate.php run` OR import schema: `mysql -u root -p icjkenya < database/schema.sql`
   - The backend reads database credentials from environment variables in `.env`

3. **Configure Web Server**
   - Point your web server document root to the `php-api` directory
   - Ensure `.htaccess` is enabled (for Apache)
   - For Nginx, configure URL rewriting to `index.php`

4. **Set Permissions**
   ```bash
   chmod 755 php-api/
   chmod 777 php-api/uploads/ # if using file uploads
   ```

5. **Update Configuration**
   - Edit `.env` with your specific settings
   - Change JWT secret key for production
   - Update email SMTP settings
   - Set correct database credentials

## API Endpoints

### Authentication
- `POST /api/v1/auth/signup` - User registration
- `POST /api/v1/auth/login` - User login
- `POST /api/v1/auth/logout` - User logout
- `PUT /api/v1/auth/users/reset-password` - Reset password
- `DELETE /api/v1/auth/deleteAccount/{email}` - Delete account
- `GET /api/v1/auth/me` - Get current user
- `POST /api/v1/auth/forgotPassword` - Request password reset

### Posts
- `POST /api/v1/posts/createPost` - Create new post (with file uploads)
- `GET /api/v1/posts/allPosts` - Get all posts (paginated)
- `GET /api/v1/posts/{id}/image` - Get post image
- `GET /api/v1/posts/{id}/pdf` - Get post PDF
- `GET /api/v1/posts/post/{id}` - Get specific post
- `DELETE /api/v1/posts/deletePost/{id}` - Delete post
- `GET /api/v1/posts/allPostsByUserId/{userId}` - Get posts by user

### Forums
- `POST /api/v1/forum/createForum` - Create new forum
- `GET /api/v1/forum/getAllForums` - Get all forums (paginated)
- `GET /api/v1/forum/{id}` - Get forum by ID
- `POST /api/v1/forum/{forumId}/join` - Join forum
- `GET /api/v1/forum/{email}/forums` - Get user's forums
- `GET /api/v1/forum/{id}/members` - Get member count
- `POST /api/v1/forum/comment` - Create forum comment
- `POST /api/v1/forum/createDiscussion` - Create discussion
- `GET /api/v1/forum/getAllForumDiscussions/{forum_id}` - Get discussions
- `POST /api/v1/forum/createReply` - Create reply
- `GET /api/v1/forum/replies/{conversationId}` - Get replies
- `GET /api/v1/forum/nestedReplies/{parentId}` - Get nested replies

### Chat
- `GET /api/v1/chat/conversations` - Get user conversations
- `POST /api/v1/chat/send` - Send message
- `GET /api/v1/chat/messages/{conversationId}` - Get conversation messages

## Authentication

The API uses JWT (JSON Web Tokens) for authentication. Include the token in the Authorization header:

```
Authorization: Bearer your-jwt-token-here
```

## File Uploads

For file uploads (posts), use multipart/form-data:
- `postRequest`: JSON data for the post
- `mediaFile`: Image file (optional)
- `documentFile`: PDF file (optional)

## Error Handling

All endpoints return consistent JSON responses:

**Success Response:**
```json
{
  "data": { ... },
  "message": "Success message"
}
```

**Error Response:**
```json
{
  "error": "Error message",
  "errors": { ... } // Optional validation errors
}
```

## CORS

The API supports CORS for the following origins:
- `https://icjkenya.netlify.app`
- `http://localhost:5173`
- `http://localhost:3000`

Update `config/config.php` to modify allowed origins.

## Security Features

- JWT token authentication
- Password hashing with bcrypt
- SQL injection prevention with prepared statements
- File upload validation
- CORS protection
- XSS protection headers
- Input validation and sanitization

## Database Schema

The application uses PostgreSQL with the following main tables:
- `users` - User accounts
- `posts` - User posts with media
- `discussion_forums` - Forum topics
- `forum_memberships` - User forum memberships
- `forum_comments` - Forum comments
- `conversations` - Forum discussions
- `replies` - Threaded replies
- `chat_conversations` - Chat conversations
- `chat_messages` - Chat messages
- `likes` - Post likes

## Logging

Application logs are written to PHP error log. In production, configure proper log rotation and monitoring.

## Production Deployment

1. Set `APP_ENV` to `production` in `config/config.php`
2. Use strong, random JWT secret key
3. Configure proper SSL/HTTPS
4. Set up database connection pooling
5. Configure proper PHP memory limits and execution time
6. Set up monitoring and logging
7. Configure backup strategy for database
8. Use a process manager like PHP-FPM with proper workers

## Development

For development, you can use PHP's built-in server:
```bash
cd php-api
php -S localhost:8000
```

However, for full functionality (file uploads, .htaccess), use Apache or Nginx.

## Support

This API provides the same functionality as the original Java Spring Boot version, converted to vanilla PHP for easier deployment and maintenance.
