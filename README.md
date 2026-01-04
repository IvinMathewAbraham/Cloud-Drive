# Mini Google Drive (PHP + MySQL)

A lightweight cloud file storage system built using **HTML**, **Bootstrap**, **PHP**, and **MySQL**.  
This project allows users to securely upload, manage, download, and delete files — similar to a simplified Google Drive.

---

## 🚀 Features

- User authentication (Register / Login / Logout)
- Secure file uploads with validation
- Personal file dashboard (My Files)
- File download & delete
- Session-based access control
- Clean, responsive UI using Bootstrap
- Ready for deployment on AWS EC2 (Ubuntu)

---

## 🛠️ Tech Stack

| Layer | Technology |
|------|-----------|
| Frontend | HTML5, Bootstrap 5 |
| Backend | PHP 8.x |
| Database | MySQL |
| Server | Apache (Ubuntu) |
| Security | Sessions, Prepared Statements, MIME validation |

---

## 📂 Project Structure

mini-drive/
│
├── public/
│ ├── index.php # User dashboard
│ ├── login.php
│ ├── register.php
│ ├── upload.php
│ ├── download.php
│ └── logout.php
│
├── uploads/ # Stored files (non-public)
│
├── includes/
│ ├── db.php # Database connection
│ ├── auth.php # Session & auth helpers
│ └── config.php # App configuration
│
├── assets/
│ └── css/
│ └── style.css
│
├── .env
└── README.md

pgsql
Copy code

---

## 🧱 Database Schema

### `users` Table
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
files Table
sql
Copy code
CREATE TABLE files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100),
    size BIGINT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
🔐 Security Measures
Password hashing using password_hash()

Session-based authentication

Prepared SQL statements (SQL Injection protection)

MIME type & file size validation

Secure file renaming

File ownership checks before download/delete

Uploads directory not directly accessible

⚙️ Setup Instructions (Local / EC2)
1️⃣ Clone Repository
bash
Copy code
git clone https://github.com/your-username/mini-google-drive.git
cd mini-google-drive
2️⃣ Configure Environment
Create a .env file:

env
Copy code
DB_HOST=localhost
DB_NAME=mini_drive
DB_USER=root
DB_PASS=your_password
3️⃣ Import Database
bash
Copy code
mysql -u root -p mini_drive < schema.sql
4️⃣ Set Permissions
bash
Copy code
chmod -R 755 uploads/
5️⃣ Run Application
Open in browser:

ruby
Copy code
http://localhost/mini-drive/public
☁️ AWS EC2 Deployment
Ubuntu 20.04 / 22.04

Install Apache, PHP, MySQL (LAMP stack)

Upload project via Git or SCP

Configure Apache Virtual Host

Ensure uploads/ is writable

Use public IP or domain

📌 Future Enhancements
Folder support

Public file sharing (read-only links)

File preview (PDF/Image)

Storage usage limits

Admin dashboard

Search and pagination

🎯 Learning Outcomes
PHP CRUD operations

Secure file handling

Authentication systems

Database relationships

AWS EC2 deployment

Real-world backend project structure

📜 License
This project is open-source and available under the MIT License.

🙌 Author
Ivin Mathew Abraham
