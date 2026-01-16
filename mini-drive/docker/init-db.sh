#!/bin/bash
# MiniDrive - Database Initialization Script
# Run this script after starting Docker containers

set -e

echo "🚀 MiniDrive Database Initialization"
echo "====================================="

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL to be ready..."
sleep 10

# Run database setup script
echo "📊 Setting up database tables..."
docker exec minidrive-app php /var/www/html/includes/setup-db.php

if [ $? -eq 0 ]; then
    echo "✅ Database initialized successfully!"
    echo ""
    echo "🌐 Access MiniDrive at: http://localhost:8080"
    echo "📝 phpMyAdmin at: http://localhost:8081 (if dev profile enabled)"
    echo ""
    echo "Default test user (create via registration):"
    echo "  Email: admin@example.com"
    echo "  Password: (set your own)"
else
    echo "❌ Database initialization failed!"
    exit 1
fi
