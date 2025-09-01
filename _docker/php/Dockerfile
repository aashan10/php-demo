# Dockerfile for PHP-FPM
FROM php:8.2-fpm-alpine

# Install PHP extensions
# pdo_mysql for database connection
# mysqli for MySQLi extension (often used with PDO)
# opcache for performance
# pcntl for the One Billion Rows Challenge command
RUN docker-php-ext-install pdo_mysql mysqli opcache pcntl

# Set working directory
WORKDIR /var/www/html

# Copy application code (will be mounted as a volume in docker-compose, but good for image build)
# COPY . /var/www/html # This line is commented out because we will mount the code as a volume

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
