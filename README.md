# Cloud-Drive

Online File Storage & Sharing System

A secure, web-based file storage and sharing platform built using PHP, MySQL, HTML, and Bootstrap, deployed on AWS EC2 (Ubuntu).
This project demonstrates real-world backend concepts such as secure file handling, authentication, access control, and cloud deployment.

🚀 Features
User Features

User registration & login

Upload files securely

Download owned files

Create folders (nested support)

Delete files

View storage usage

Share files using secure public links (token-based)

Admin Features

View all users

Monitor storage usage

View total files & activity

Disable users (optional)

🛠 Tech Stack
Layer	Technology
Frontend	HTML5, Bootstrap 5, JavaScript
Backend	PHP 8.x
Database	MySQL (InnoDB)
Server	Apache
OS	Ubuntu 22.04
Cloud	AWS EC2
🗂 Project Structure
/var/www/html/
│
├── auth/           # Login, Register, Logout
├── files/          # Upload, Download, Delete, Share
├── folders/        # Folder management
├── dashboard/      # User dashboard
├── admin/          # Admin dashboard
├── config/         # Database config
├── assets/         # CSS, JS
└── index.php


Private file storage (outside web root):

/var/storage/files/

🧱 Database Schema (Core Tables)

users – authentication, roles, storage usage

folders – hierarchical folder structure

files – file metadata (actual files stored on disk)

shared_links – secure public sharing tokens

file_activity – audit logs

🔐 Security Highlights

Files stored outside public web root

Randomized stored filenames

MIME type validation (not extension-based)

File size limits

Password hashing (password_hash)

Session-based authentication

Ownership checks on download (prevents IDOR)

Prepared SQL statements (PDO)

☁ Deployment (AWS EC2)

Ubuntu 22.04 EC2 instance

Apache + PHP 8.x

MySQL

Proper Linux permissions (www-data)

Security Groups allowing HTTP (80) & SSH (22)

This project is designed to run entirely on EC2, with the option to later migrate storage to AWS S3.

📈 Why This Project Matters

✔ Real backend file system handling
✔ Cloud deployment experience
✔ Security-first design
✔ Scalable architecture
✔ Portfolio & interview ready

This is not a demo CRUD app—it mirrors how real cloud storage systems work internally.

🔮 Future Enhancements

Chunked uploads for large files

File previews (PDF / images)

Trash & restore feature

Storage quotas per user

File encryption at rest

AWS S3 integration

📄 License

This project is for learning and portfolio purposes.
Feel free to fork and extend.
