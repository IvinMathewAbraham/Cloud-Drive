#!/bin/bash
# MiniDrive - Quick Start Script
# Run this script to set up and start MiniDrive with Docker

set -e

echo "🚀 MiniDrive Quick Start"
echo "======================="
echo ""

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker Desktop first."
    echo "   Download from: https://www.docker.com/products/docker-desktop"
    exit 1
fi

# Check if Docker Compose is available
if ! docker compose version &> /dev/null; then
    echo "❌ Docker Compose is not available. Please update Docker Desktop."
    exit 1
fi

echo "✅ Docker is installed"
echo ""

# Check if .env exists
if [ ! -f .env ]; then
    echo "📝 Creating .env file from .env.example..."
    cp .env.example .env
    echo "✅ .env file created"
    echo "⚠️  Please edit .env and set your passwords before continuing."
    echo ""
    read -p "Press Enter after editing .env (or Ctrl+C to exit)..."
fi

# Create uploads directory
if [ ! -d uploads ]; then
    echo "📁 Creating uploads directory..."
    mkdir -p uploads
    echo "✅ Uploads directory created"
fi

# Build and start containers
echo ""
echo "🏗️  Building Docker containers (this may take a few minutes)..."
docker compose up -d --build

echo ""
echo "⏳ Waiting for services to be ready..."
sleep 15

# Initialize database
echo ""
echo "📊 Initializing database..."
docker compose exec app php /var/www/html/includes/setup-db.php

echo ""
echo "✅ MiniDrive is ready!"
echo ""
echo "🌐 Access your application at: http://localhost:8080"
echo "📝 phpMyAdmin at: http://localhost:8081 (optional)"
echo ""
echo "📋 Next steps:"
echo "   1. Open http://localhost:8080 in your browser"
echo "   2. Click 'Register' to create your first account"
echo "   3. Start uploading files!"
echo ""
echo "To stop: docker compose down"
echo "To view logs: docker compose logs -f"
echo ""
