#!/bin/sh
set -e

echo "Waiting for MySQL..."
until php -r "
\$conn = @new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASSWORD'),
    getenv('DB_NAME')
);
exit(\$conn->connect_errno ? 1 : 0);
"; do
  sleep 2
done

echo "MySQL is up - running setup-db.php"
php /var/www/html/includes/setup-db.php || true

echo "Starting Apache"
exec apache2-foreground