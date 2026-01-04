# 📘 NoteBlock  
## Student Notes & PYQ Aggregator Platform  
**(PHP + MySQL | AWS EC2 | Ubuntu)**

---

## 🚀 Introduction

**NoteBlock** is a centralized academic platform that allows students to **upload, browse, search, and download** study materials such as:

- Class Notes  
- Previous Year Question Papers (PYQs)  
- Solved Papers  

The system is built using **HTML, Bootstrap, Core PHP, and MySQL**, and is designed to be deployed on an **AWS EC2 instance running Ubuntu**.  
It follows a **REST-style API architecture** and implements **complete CRUD functionality**.

This project is suitable for:
- Final year academic projects  
- Portfolio projects  
- Viva / interview demonstrations  

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML, CSS, Bootstrap |
| Backend | Core PHP (PDO) |
| Database | MySQL (InnoDB) |
| Server | Apache / Nginx |
| OS | Ubuntu 22.04 |
| Hosting | AWS EC2 |

---

## ✨ Core Features

### 👨‍🎓 Student
- Register & login
- Browse approved notes and PYQs
- Search & filter by subject, semester, year
- Download study materials
- Upvote useful documents

### ✍️ Contributor
- Upload notes / PYQs / solved papers
- Add metadata (subject, semester, year)
- Track downloads & upvotes

# 📘 NoteBlock — Student Notes & PYQ Aggregator

A lightweight academic platform to upload, discover, and download class notes, previous-year question papers (PYQs) and solved papers.

## Table of Contents
- [Introduction](#introduction)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Quick Start](#quick-start)
- [Project Structure](#project-structure)
- [API Endpoints](#api-endpoints)
- [Database Schema (summary)](#database-schema-summary)
- [Deployment (AWS EC2)](#deployment-aws-ec2)
- [Contributing](#contributing)
- [License](#license)

## Introduction

NoteBlock provides a simple way for students and contributors to share study materials. It is implemented with Core PHP, uses MySQL for storage, and exposes REST-style API endpoints for integration.

## Features

- Student: register/login, browse, search, download, upvote documents
- Contributor: upload documents with metadata (subject, semester, year)
- Admin: review/approve uploads, manage users, moderate content
- Tracking: download counts and upvotes

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML, CSS, Bootstrap |
| Backend | Core PHP (PDO) |
| Database | MySQL (InnoDB) |
| Server | Apache / Nginx |
| OS | Ubuntu (recommended) |
| Hosting | AWS EC2 |

## Quick Start

Requirements
- PHP 8+, Apache/Nginx, MySQL

Steps
1. Clone the repository into your web root.
2. Import `sql/schema.sql` into a MySQL database named `noteblock`.
3. Configure DB credentials in `includes/db.php`.
4. Ensure the `uploads/` directory is writable and stored outside public execution scope if possible.

## Project Structure

noteblock/

- public/ — web entry points (index.php, login.php, upload.php, search.php)
- api/ — REST endpoints (auth/, users/, documents/, votes/, admin/)
- admin/ — admin UI pages
- includes/ — shared helpers (`db.php`, `auth.php`, `functions.php`)
- uploads/ — stored PDF files
- assets/ — CSS/JS
- sql/schema.sql — database schema

## API Endpoints

Authentication

```http
POST /api/auth/register.php
POST /api/auth/login.php
POST /api/auth/logout.php
```

Users (admin)

```http
GET  /api/users/list.php
POST /api/users/update-role.php
POST /api/users/delete.php
```

Documents

```http
POST /api/documents/upload.php
GET  /api/documents/list.php
GET  /api/documents/view.php?id={id}
POST /api/documents/update.php
POST /api/documents/delete.php
```

Search

```http
GET /api/search.php?subject=DBMS&semester=S4&year=2024
```

Downloads

```http
GET /api/documents/download.php?id={id}
```

Votes

```http
POST /api/votes/add.php
POST /api/votes/remove.php
GET  /api/votes/status.php?document_id={id}
```

Admin moderation

```http
GET  /api/admin/pending.php
POST /api/admin/approve.php
POST /api/admin/reject.php
```

## Database Schema (summary)

Important tables (high level):

- `users` — id, name, email, password (hashed), role, is_active, created_at
- `documents` — id, title, file_path, type (notes/pyq/solved), subject, semester, year, uploader_id, status, downloads, upvotes, created_at
- `votes` — user_id, document_id, voted_at (PK: user_id, document_id)
- `downloads` — id, user_id, document_id, downloaded_at
- `admin_logs` — id, admin_id, action, target_id, created_at

Indexes: add indexes on `documents(subject, semester, year, status)` and consider a FULLTEXT index on `title` and `subject` for better search.

Security notes

- Hash passwords (use `password_hash`).
- Use prepared statements (PDO) for DB queries.
- Validate MIME types for uploads and store files outside the public execution scope.
- Enforce role-based access control for admin actions.

## Deployment (AWS EC2 — Ubuntu)

Quick steps

```bash
sudo apt update
sudo apt install apache2 php php-mysql mysql-server
sudo ufw allow 'Apache Full'
# Place project in /var/www/html/noteblock and set permissions
```

Import the SQL schema and configure database credentials in `includes/db.php`.

## Contributing

1. Fork the repo and create a feature branch.
2. Open a pull request with a clear description of changes.
3. Keep migrations or schema changes in `sql/` and document them here.

## License

Specify your license here (e.g. MIT) or add project-specific terms.

---

If you'd like, I can also:
- add a minimal `schema.sql` example file (cleaned),
- generate a sample `.env.example` and update `includes/db.php` to use environment variables,
- or run a quick lint/format pass on markdown.

