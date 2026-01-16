# MiniDrive - Docker Deployment Guide

Complete guide for running MiniDrive with Docker, both locally and on AWS EC2.

---

## 📋 Prerequisites

### Local Development
- **Docker Desktop** (Windows/Mac) or **Docker Engine** (Linux)
- **Docker Compose** v2.0+
- **Git** (to clone the repository)
- 2GB+ available RAM
- 5GB+ available disk space

### AWS EC2 Production
- **AWS Account** with EC2 access
- **Ubuntu 22.04 LTS** on t2.micro instance
- **Security Group** configured (ports 80, 443, 22)
- **Elastic IP** (recommended for production)

---

## 🏠 Local Development Setup

### Step 1: Prepare Environment

```bash
# Clone the repository (if needed)
cd c:\wamp64\www\Cloud\mini-drive

# Copy environment file
cp .env.example .env

# Edit .env with your preferences
notepad .env
```

**Important:** For local development, keep these values in `.env`:
```
DB_HOST=db
DB_USER=mini_user
DB_PASSWORD=ChangeThisToAStrongPassword123!
DB_NAME=mini_drive
APP_URL=http://localhost:8080
```

### Step 2: Build and Start Containers

```bash
# Build and start services
docker compose up -d --build

# View logs
docker compose logs -f

# Check running containers
docker compose ps
```

### Step 3: Initialize Database

**Option A - Automatic (Bash - Git Bash on Windows):**
```bash
chmod +x docker/init-db.sh
./docker/init-db.sh
```

**Option B - Manual:**
```bash
# Wait for MySQL to be ready (10-15 seconds)
docker compose exec app php /var/www/html/includes/setup-db.php
```

**Option C - Browser:**
Navigate to: `http://localhost:8080/includes/setup-db.php`

### Step 4: Access Application

- **MiniDrive**: http://localhost:8080
- **phpMyAdmin** (dev only): http://localhost:8081

### Step 5: Create First User

1. Click "Register" on the login page
2. Create an account with your email and password
3. Login and start uploading files!

---

## 🔧 Development Commands

### Container Management
```bash
# Start containers
docker compose up -d

# Stop containers
docker compose down

# Rebuild containers
docker compose up -d --build

# View logs
docker compose logs -f app
docker compose logs -f db

# Start with phpMyAdmin
docker compose --profile dev up -d
```

### Database Operations
```bash
# Access MySQL CLI
docker compose exec db mysql -u mini_user -p mini_drive

# Backup database
docker compose exec db mysqldump -u mini_user -p mini_drive > backup.sql

# Restore database
docker compose exec -T db mysql -u mini_user -p mini_drive < backup.sql

# Reset database
docker compose exec app php /var/www/html/includes/setup-db.php
```

### Application Operations
```bash
# Access container shell
docker compose exec app bash

# Check PHP version
docker compose exec app php -v

# Check Apache status
docker compose exec app service apache2 status

# View Apache error logs
docker compose exec app tail -f /var/log/apache2/error.log

# Clear uploads folder
docker compose exec app rm -rf /var/www/html/uploads/*
```

### File Permissions
```bash
# Fix upload permissions (if needed)
docker compose exec app chown -R www-data:www-data /var/www/html/uploads
docker compose exec app chmod 755 /var/www/html/uploads
```

---

## ☁️ AWS EC2 Production Deployment

### Step 1: Launch EC2 Instance

1. **Launch Instance:**
   - AMI: Ubuntu Server 22.04 LTS
   - Instance Type: t2.micro (Free Tier)
   - Storage: 20GB gp2 SSD
   - Key Pair: Create/select SSH key

2. **Configure Security Group:**
   ```
   Type            Protocol    Port    Source
   SSH             TCP         22      Your IP
   HTTP            TCP         80      0.0.0.0/0
   HTTPS           TCP         443     0.0.0.0/0
   Custom TCP      TCP         8080    Your IP (for testing)
   ```

3. **Allocate Elastic IP** (recommended):
   - Associate with your instance for static IP

### Step 2: Connect to EC2

```bash
# SSH into your instance (Windows - use Git Bash or PuTTY)
ssh -i "your-key.pem" ubuntu@your-ec2-public-ip

# Update system
sudo apt update && sudo apt upgrade -y
```

### Step 3: Install Docker

```bash
# Install Docker
sudo apt install -y docker.io

# Install Docker Compose Plugin
sudo apt install -y docker-compose-plugin

# Add user to docker group
sudo usermod -aG docker $USER

# Enable Docker service
sudo systemctl start docker
sudo systemctl enable docker

# Verify installation
docker --version
docker compose version

# Log out and back in for group changes to take effect
exit
```

### Step 4: Deploy Application

```bash
# SSH back in
ssh -i "your-key.pem" ubuntu@your-ec2-public-ip

# Create application directory
mkdir -p ~/minidrive
cd ~/minidrive

# Clone repository (or upload files via SCP)
git clone https://github.com/yourusername/minidrive.git .

# Or upload via SCP from your local machine:
# scp -i "your-key.pem" -r c:/wamp64/www/Cloud/mini-drive/* ubuntu@your-ec2-ip:~/minidrive/

# Create .env file
cp .env.example .env
nano .env
```

**Update `.env` for production:**
```
DB_HOST=db
DB_USER=mini_user
DB_PASSWORD=VeryStrongProductionPassword123!
DB_NAME=mini_drive
DB_ROOT_PASSWORD=AnotherStrongRootPassword456!
APP_URL=http://your-ec2-public-ip
```

### Step 5: Start Application

```bash
# Build and start containers
docker compose up -d --build

# Wait for containers to be healthy
docker compose ps

# Initialize database
docker compose exec app php /var/www/html/includes/setup-db.php

# Verify logs
docker compose logs -f
```

### Step 6: Configure for Port 80 (Production)

**Option A - Update docker-compose.yml:**
```yaml
services:
  app:
    ports:
      - "80:80"  # Change from 8080:80
```

**Option B - NGINX Reverse Proxy (Recommended):**

```bash
# Install NGINX
sudo apt install -y nginx

# Create NGINX config
sudo nano /etc/nginx/sites-available/minidrive
```

Add this configuration:
```nginx
server {
    listen 80;
    server_name your-domain.com;  # or EC2 IP

    client_max_body_size 10M;

    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Enable the site:
```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/minidrive /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Restart NGINX
sudo systemctl restart nginx

# Access at http://your-ec2-ip
```

### Step 7: Enable HTTPS (Optional with Let's Encrypt)

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Get SSL certificate (requires domain name)
sudo certbot --nginx -d your-domain.com

# Auto-renewal is set up automatically
sudo certbot renew --dry-run
```

---

## 🔒 Security Best Practices

### 1. Database Security
- ✅ Non-root MySQL user with limited privileges
- ✅ Strong passwords (20+ characters, mixed case, numbers, symbols)
- ✅ Database not exposed externally in production (remove port 3306)
- ✅ Regular backups

```bash
# Remove MySQL port exposure in production
# Comment out in docker-compose.yml:
# ports:
#   - "3306:3306"
```

### 2. File System Security
- ✅ Application files read-only (except `uploads/`)
- ✅ `uploads/` directory not web-accessible (served via PHP)
- ✅ `.env` file protected from web access
- ✅ `includes/` directory blocked in Apache config

### 3. Environment Variables
- ✅ Never commit `.env` to Git
- ✅ Use Docker secrets for production (advanced)
- ✅ Rotate passwords regularly

### 4. Application Security
- ✅ Session timeouts (1 hour)
- ✅ Rate limiting on uploads
- ✅ File type validation
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection headers

### 5. Network Security
- ✅ Use isolated Docker network
- ✅ HTTPS in production (with Let's Encrypt)
- ✅ Firewall rules (AWS Security Groups)
- ✅ Fail2ban for SSH protection (optional)

```bash
# Install Fail2ban
sudo apt install -y fail2ban
sudo systemctl enable fail2ban
```

---

## 📊 Monitoring and Maintenance

### Container Health
```bash
# Check container status
docker compose ps

# Check resource usage
docker stats

# Check container health
docker inspect minidrive-app | grep -A 5 Health
```

### Application Logs
```bash
# Real-time logs
docker compose logs -f app

# Apache error logs
docker compose exec app tail -f /var/log/apache2/error.log

# Apache access logs
docker compose exec app tail -f /var/log/apache2/access.log
```

### Database Monitoring
```bash
# MySQL status
docker compose exec db mysqladmin -u root -p status

# Database size
docker compose exec db mysql -u root -p -e "
SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_schema = 'mini_drive'
GROUP BY table_schema;
"
```

### Backup Strategy
```bash
# Create backup script
cat > ~/backup.sh << 'EOF'
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR=~/backups
mkdir -p $BACKUP_DIR

# Backup database
docker compose exec -T db mysqldump -u mini_user -p${DB_PASSWORD} mini_drive > $BACKUP_DIR/db_$DATE.sql

# Backup uploads
tar -czf $BACKUP_DIR/uploads_$DATE.tar.gz uploads/

# Keep only last 7 days
find $BACKUP_DIR -type f -mtime +7 -delete

echo "Backup completed: $DATE"
EOF

chmod +x ~/backup.sh

# Add to crontab (daily at 2 AM)
crontab -e
# Add: 0 2 * * * /home/ubuntu/backup.sh >> /home/ubuntu/backup.log 2>&1
```

---

## 🚀 Performance Optimization

### 1. Database Optimization
- Ensure indexes exist (check `setup-db.php`)
- Regular OPTIMIZE TABLE commands
- Monitor slow queries

### 2. File Storage
- Consider AWS S3 for file storage (over 100GB)
- Use object storage for better scalability
- Implement CDN for file downloads

### 3. Caching
- Enable OPcache for PHP
- Use Redis for session storage (advanced)
- Browser caching via Apache headers

### 4. Resource Limits
```yaml
# Add to docker-compose.yml services
deploy:
  resources:
    limits:
      cpus: '0.50'
      memory: 512M
    reservations:
      memory: 256M
```

---

## 🐛 Troubleshooting

### Issue: Container won't start
```bash
# Check logs
docker compose logs app
docker compose logs db

# Common fixes
docker compose down
docker compose up -d --build
```

### Issue: Database connection failed
```bash
# Verify database is healthy
docker compose ps

# Check environment variables
docker compose exec app env | grep DB_

# Reset database
docker compose down -v
docker compose up -d
```

### Issue: Uploads fail
```bash
# Check permissions
docker compose exec app ls -la /var/www/html/uploads

# Fix permissions
docker compose exec app chown -R www-data:www-data /var/www/html/uploads
docker compose exec app chmod 755 /var/www/html/uploads
```

### Issue: Port 8080 already in use
```bash
# Find process using port 8080
# Windows:
netstat -ano | findstr :8080

# Kill process or change port in docker-compose.yml
ports:
  - "8081:80"  # Use different port
```

### Issue: Out of disk space
```bash
# Check Docker disk usage
docker system df

# Clean up unused resources
docker system prune -a --volumes

# Remove old images
docker image prune -a
```

---

## 🔄 Updates and Upgrades

### Updating Application Code
```bash
# Pull latest code
git pull origin main

# Rebuild containers
docker compose up -d --build

# Run any new migrations
docker compose exec app php /var/www/html/includes/setup-db.php
```

### Updating Docker Images
```bash
# Pull latest base images
docker compose pull

# Rebuild
docker compose up -d --build
```

---

## 📦 Migration to S3 (Future Scaling)

When your storage needs exceed 100GB, migrate to AWS S3:

1. **Create S3 Bucket**
2. **Install AWS SDK for PHP**
3. **Update upload/download handlers**
4. **Migrate existing files**

This keeps your EC2 instance lightweight while storing files in scalable object storage.

---

## 📞 Support

For issues:
1. Check logs: `docker compose logs -f`
2. Review this documentation
3. Open GitHub issue
4. Check AWS CloudWatch logs (if on EC2)

---

## ✅ Production Checklist

Before going live:

- [ ] Strong passwords in `.env`
- [ ] `.env` not in Git repository
- [ ] MySQL port not exposed (remove 3306 mapping)
- [ ] phpMyAdmin disabled (remove or protect)
- [ ] HTTPS enabled (Let's Encrypt)
- [ ] Backups configured (database + uploads)
- [ ] Monitoring setup (CloudWatch, logs)
- [ ] Security Group restricted (SSH to your IP only)
- [ ] Elastic IP assigned
- [ ] Domain name configured (optional)
- [ ] Firewall enabled (ufw)
- [ ] Fail2ban installed
- [ ] Tested file upload/download
- [ ] Tested user registration/login

---

**🎉 Your MiniDrive cloud storage is now running in Docker!**

Access: http://localhost:8080 (local) or http://your-ec2-ip (production)
