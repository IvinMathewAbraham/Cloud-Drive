# MiniDrive Docker - Quick Reference

## Essential Commands

### Starting & Stopping
```bash
# Start all services
docker compose up -d

# Start with build
docker compose up -d --build

# Stop all services
docker compose down

# Stop and remove volumes
docker compose down -v

# Restart services
docker compose restart
```

### Viewing Logs
```bash
# All logs
docker compose logs -f

# Application logs only
docker compose logs -f app

# Database logs only
docker compose logs -f db

# Last 100 lines
docker compose logs --tail=100
```

### Database Operations
```bash
# Access MySQL CLI
docker compose exec db mysql -u mini_user -p mini_drive

# Run setup script
docker compose exec app php /var/www/html/includes/setup-db.php

# Backup database
docker compose exec db mysqldump -u mini_user -p mini_drive > backup.sql

# Restore database
docker compose exec -T db mysql -u mini_user -p mini_drive < backup.sql
```

### Container Management
```bash
# List running containers
docker compose ps

# View container resource usage
docker stats

# Access app container shell
docker compose exec app bash

# Access database container shell
docker compose exec db bash
```

### File Operations
```bash
# Fix upload permissions
docker compose exec app chown -R www-data:www-data /var/www/html/uploads

# View Apache error log
docker compose exec app tail -f /var/log/apache2/error.log

# View Apache access log
docker compose exec app tail -f /var/log/apache2/access.log
```

### Cleanup
```bash
# Remove stopped containers
docker compose rm

# Remove all unused Docker resources
docker system prune -a

# Remove volumes (WARNING: deletes data)
docker volume prune
```

## Troubleshooting

### Port Already in Use
```bash
# Windows: Find process using port 8080
netstat -ano | findstr :8080

# Change port in docker-compose.yml
ports:
  - "8081:80"  # Use different port
```

### Permission Issues
```bash
# Reset upload directory permissions
docker compose exec app chown -R www-data:www-data /var/www/html/uploads
docker compose exec app chmod 755 /var/www/html/uploads
```

### Database Connection Errors
```bash
# Check if database is running
docker compose ps

# Restart database
docker compose restart db

# Wait for healthy status
docker compose ps db
```

### Container Won't Start
```bash
# View detailed logs
docker compose logs app

# Rebuild from scratch
docker compose down -v
docker compose up -d --build
```

## Quick Access URLs

- **Application**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081
- **MySQL**: localhost:3306

## Environment Variables

Edit `.env` to customize:

```env
DB_HOST=db
DB_USER=mini_user
DB_PASSWORD=your_password
DB_NAME=mini_drive
APP_URL=http://localhost:8080
```

## Production Deployment

For AWS EC2 deployment, see [DOCKER_SETUP.md](DOCKER_SETUP.md) - complete guide with:
- EC2 instance setup
- Security group configuration
- NGINX reverse proxy
- HTTPS with Let's Encrypt
- Backup strategies
- Monitoring setup

## Development Mode

Start with phpMyAdmin:
```bash
docker compose --profile dev up -d
```

## Health Checks

```bash
# Check application health
curl http://localhost:8080

# Check database health
docker compose exec db mysqladmin ping -h localhost -u root -p
```

## Backup Strategy

```bash
# Create backup directory
mkdir -p backups

# Backup database
docker compose exec -T db mysqldump -u mini_user -p${DB_PASSWORD} mini_drive > backups/db_$(date +%Y%m%d).sql

# Backup uploads
tar -czf backups/uploads_$(date +%Y%m%d).tar.gz uploads/
```

## Useful Tips

1. **Always use docker compose** (not docker-compose with hyphen - that's the old version)
2. **Check logs first** when troubleshooting
3. **Use named volumes** for database (already configured)
4. **Use bind mounts** for uploads (already configured)
5. **Keep .env secure** - never commit to Git
6. **Regular backups** - both database and uploads
7. **Monitor disk space** - `docker system df`
8. **Update regularly** - `docker compose pull && docker compose up -d`

---

For complete documentation, see [DOCKER_SETUP.md](DOCKER_SETUP.md)
