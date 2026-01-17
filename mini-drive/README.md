# MiniDrive - Cloud Storage Solution

A lightweight, modern cloud storage application built with PHP, MySQL, and Tailwind CSS. Perfect for personal file management and sharing.

## Features

- 🔐 **Secure Authentication**: User registration and login with password hashing
- 📁 **File Management**: Upload, download, and delete files
- 👥 **File Sharing**: Share files with other users (viewer role)
- 💾 **Storage Quota**: 50MB per user with real-time usage tracking
- 📊 **Rate Limiting**: Upload limits (20 per hour) to prevent abuse
- 🔒 **File Encryption**: AES-256 encryption for files over 1MB
- 🎨 **Modern UI**: Beautiful Tailwind CSS design
- ⚡ **Fast & Lightweight**: Optimized for shared hosting

## Requirements

- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx with mod_rewrite
- 50MB+ disk space per user
- AWS EC2 Free Tier compatible (t2.micro, 20GB EBS)

## Installation

### 1. Create Database

```sql
CREATE DATABASE mini_drive;
USE mini_drive;
```

### 2. Configure Environment

Edit `.env`:
```
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=mini_drive
APP_URL=http://your-domain/Cloud/mini-drive
```

### 3. Setup Database Tables

Access via browser:
```
http://your-domain/Cloud/mini-drive/includes/setup-db.php
```

Or run in PHP terminal:
```bash
php includes/setup-db.php
```

### 4. Create Uploads Directory

```bash
chmod 755 uploads/
```

## File Structure

```
mini-drive/
├── public/              # Web accessible files
│   ├── index.php       # Dashboard
│   ├── login.php       # Login page
│   ├── register.php    # Registration page
│   ├── upload.php      # File upload handler
│   ├── download.php    # File download handler
│   ├── delete-file.php # File deletion
│   ├── preview-file.php # File preview
│   ├── share-file.php  # File sharing
│   ├── logout.php      # Logout
│   └── js/
│       └── dashboard.js # Dashboard JavaScript
├── includes/           # Backend logic
│   ├── config.php      # Configuration
│   ├── db.php          # Database connection
│   ├── auth.php        # Authentication
│   └── setup-db.php    # Database setup
├── uploads/            # User files (non-public)
├── .env                # Environment variables
├── .gitignore          # Git ignore file
├── STYLING_CHANGES.md  # Styling documentation
└── TAILWIND_BROWSER_BUILD.md # Tailwind setup guide
```

## Usage

1. **Register**: Create a new account at `/public/register.php`
2. **Login**: Sign in at `/public/login.php`
3. **Upload**: Drag & drop or click to upload files
4. **Share**: Click the share icon to share files with others
5. **Download**: Click the download icon to get your files
6. **Delete**: Remove files with the delete button

## Security Features

- ✅ Password hashing with bcrypt
- ✅ Session-based authentication with timeout
- ✅ Rate limiting on uploads
- ✅ AES-256 file encryption
- ✅ SQL injection prevention (prepared statements)
- ✅ File type validation
- ✅ File size limits
- ✅ User isolation (can't access other users' files)

## Limits

- **File Size**: 10MB max per file
- **Storage**: 50MB per user
- **Upload Rate**: 20 files per hour
- **Session Timeout**: 1 hour idle
- **Username**: 3-50 characters
- **Password**: Minimum 6 characters

## AWS EC2 Free Tier Deployment

### Recommended Setup:
- **Instance**: t2.micro (1GB RAM, 1 vCPU)
- **Storage**: 20GB gp2 SSD (sufficient for ~400 users)
- **OS**: Ubuntu 22.04 LTS

### Installation Steps:

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install LAMP stack
sudo apt install apache2 php php-mysql mysql-server -y

# Start services
sudo systemctl start apache2 mysql-server
sudo systemctl enable apache2 mysql-server

# Clone project
cd /var/www/html
git clone <your-repo> Cloud
cd Cloud/mini-drive

# Configure MySQL
sudo mysql -u root
> CREATE DATABASE mini_drive;
> CREATE USER 'mini_user'@'localhost' IDENTIFIED BY 'strong_password';
> GRANT ALL PRIVILEGES ON mini_drive.* TO 'mini_user'@'localhost';
> FLUSH PRIVILEGES;

# Update .env
nano .env

# Set permissions
chmod 755 uploads/
chmod 644 .env

# Setup database
php includes/setup-db.php
```

## Performance Optimization

- **Database Indexing**: User ID and timestamps indexed for faster queries
- **File Compression**: Large files are encrypted (reduces storage)
- **Lazy Loading**: Files loaded on demand
- **Rate Limiting**: Prevents server overload
- **Soft Deletes**: Don't physically delete files immediately

## Troubleshooting

### Upload fails
- Check `uploads/` directory permissions
- Verify file size < 10MB
- Check user storage quota
- Ensure rate limit not exceeded

### Login issues
- Clear browser cookies
- Check email format
- Verify MySQL connection in `.env`

### Database errors
- Run `php includes/setup-db.php` again
- Check MySQL credentials in `.env`
- Ensure database exists

## License

MIT License - Free to use and modify

## Support

For issues and questions, please create an issue in the repository.

---

Built with ❤️ for cloud storage lovers
