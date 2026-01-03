# Cloud-Drive

A secure, web-based file storage and sharing platform built with PHP, MySQL, HTML, and Bootstrap. Deployed on an AWS EC2 (Ubuntu) instance, this project demonstrates backend concepts like secure file handling, authentication, access control, and cloud deployment.

## Table of contents

- Features
  - User
  - Admin
- Tech stack
- Project structure
- Quickstart
- Database
- Security highlights
- Deployment notes
- Future enhancements
- License

## Features

### User

- Register and log in
- Upload files securely
- Download files you own
- Create nested folders
- Delete files
- View storage usage
- Share files using secure, token-based public links

### Admin

- View all users
- Monitor total storage usage and file counts
- Inspect activity logs
- Disable users (optional)

## Tech stack

- Frontend: HTML5, Bootstrap 5, JavaScript
- Backend: PHP 8.x
- Database: MySQL (InnoDB)
- Server: Apache on Ubuntu 22.04
- Cloud: AWS EC2

## Project structure (web root: /var/www/html)

/var/www/html/

├── auth/           # Login, register, logout
├── files/          # Upload, download, delete, share
├── folders/        # Folder management
├── dashboard/      # User dashboard
├── admin/          # Admin dashboard
├── config/         # Database and app config
├── assets/         # CSS, JS, images
└── index.php

Private file storage (stored outside the web root):

/var/storage/files/

## Quickstart

1. Clone the repo:
   git clone https://github.com/IvinMathewAbraham/Cloud-Drive.git
2. Configure your server (Ubuntu 22.04): Apache + PHP 8.x + MySQL
3. Create a MySQL database and import the schema (see /config or schema files)
4. Update database credentials in config (config/)
5. Ensure private storage path exists and is writable by www-data:
   sudo mkdir -p /var/storage/files && sudo chown -R www-data:www-data /var/storage/files
6. Point your virtual host to /var/www/html and restart Apache

## Database (core tables)

- users – authentication, roles, storage usage
- folders – hierarchical folder structure
- files – file metadata (actual files stored on disk)
- shared_links – secure public sharing tokens
- file_activity – audit logs

## Security highlights

- Files stored outside the public web root
- Randomized stored filenames to avoid collisions and guessability
- MIME type validation (server-side) — not extension-based only
- File size limits enforced
- Passwords hashed with password_hash()
- Session-based authentication
- Ownership checks on downloads to prevent IDOR
- Prepared SQL statements (PDO) to avoid SQL injection

## Deployment notes (AWS EC2)

- Ubuntu 22.04 EC2 instance
- Apache + PHP 8.x, MySQL
- Proper Linux permissions (www-data)
- Security group rules: allow HTTP (80) and SSH (22) as needed
- Designed to run on EC2; can be migrated to S3 for object storage later

## Why this project matters

- Real backend file-system handling (not just CRUD)
- Cloud deployment experience
- Security-first design and audit logging
- Scalable architecture and a strong portfolio project

## Future enhancements

- Chunked uploads for large files
- File previews (PDF, images)
- Trash & restore functionality
- Per-user storage quotas
- Encryption at rest
- AWS S3 integration

## License

This project is provided for learning and portfolio use. Feel free to fork and extend.
