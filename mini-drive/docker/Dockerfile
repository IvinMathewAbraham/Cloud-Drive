FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite headers

# Install image processing dependencies and clean apt cache
RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		libpng-dev \
		libjpeg62-turbo-dev \
		libwebp-dev \
		libfreetype6-dev \
		libavif-dev \
	&& rm -rf /var/lib/apt/lists/*

# Set ServerName to suppress warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
	&& docker-php-ext-install gd mysqli pdo pdo_mysql

# Configure Apache DocumentRoot and AllowOverride (scoped to our app)
RUN sed -i 's|/var/www/html|/var/www/html|g' /etc/apache2/sites-available/000-default.conf \
    && printf '\n<Directory /var/www/html>\n AllowOverride All\n</Directory>\n' >> /etc/apache2/sites-available/000-default.conf

# Create uploads directory and set permissions (as root)
RUN mkdir -p /var/www/html/uploads \
	&& chown -R www-data:www-data /var/www/html/uploads

# Copy entrypoint and make it executable (AS ROOT)
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["/entrypoint.sh"]

EXPOSE 80

# Note: entrypoint will run as root and then Apache will run as www-data
