@echo off
REM MiniDrive - Quick Start Script for Windows
REM Run this script to set up and start MiniDrive with Docker

echo.
echo =============================
echo  MiniDrive Quick Start
echo =============================
echo.

REM Check if Docker is installed
docker --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Docker is not installed or not in PATH
    echo Please install Docker Desktop from: https://www.docker.com/products/docker-desktop
    pause
    exit /b 1
)

echo [OK] Docker is installed
echo.

REM Check if .env exists
if not exist .env (
    echo [INFO] Creating .env file from .env.example...
    copy .env.example .env >nul
    echo [OK] .env file created
    echo.
    echo [WARNING] Please edit .env and set your passwords before continuing.
    echo           You can edit it with: notepad .env
    echo.
    pause
)

REM Create uploads directory
if not exist uploads (
    echo [INFO] Creating uploads directory...
    mkdir uploads
    echo [OK] Uploads directory created
)

REM Build and start containers
echo.
echo [INFO] Building Docker containers (this may take a few minutes)...
docker compose up -d --build

if errorlevel 1 (
    echo [ERROR] Failed to start containers
    pause
    exit /b 1
)

echo.
echo [INFO] Waiting for services to be ready...
timeout /t 15 /nobreak >nul

REM Initialize database
echo.
echo [INFO] Initializing database...
docker compose exec app php /var/www/html/includes/setup-db.php

echo.
echo =============================
echo  MiniDrive is ready!
echo =============================
echo.
echo Access your application at: http://localhost:8080
echo phpMyAdmin at: http://localhost:8081 (optional)
echo.
echo Next steps:
echo   1. Open http://localhost:8080 in your browser
echo   2. Click 'Register' to create your first account
echo   3. Start uploading files!
echo.
echo Useful commands:
echo   - Stop: docker compose down
echo   - View logs: docker compose logs -f
echo   - Restart: docker compose restart
echo.
pause
