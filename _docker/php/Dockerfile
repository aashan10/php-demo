# Dockerfile for PHP-FPM
FROM php:8.2-fpm-alpine

# Install PHP extensions
# pdo_mysql for database connection
# mysqli for MySQLi extension (often used with PDO)
# opcache for performance
# pcntl for the One Billion Rows Challenge command
RUN docker-php-ext-install pdo_mysql mysqli opcache pcntl

RUN apk add --no-cache linux-headers \
    && apk add --update --no-cache --virtual .build-dependencies $PHPIZE_DEPS\
    && pecl install xdebug \
    && pecl install redis \
    && docker-php-ext-enable xdebug \
    && docker-php-ext-enable redis \
    && pecl clear-cache \
    && apk del .build-dependencies


# Install Node.js v22+ and npm
RUN apk add --no-cache nodejs npm

# Set working directory
WORKDIR /var/www/html

RUN apk add curl

# Copy application code (will be mounted as a volume in docker-compose, but good for image build)
# COPY . /var/www/html # This line is commented out because we will mount the code as a volume

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
