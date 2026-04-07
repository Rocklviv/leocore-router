FROM php:8.2-apache

LABEL maintainer="php-router-team"
LABEL description="Lightweight MVC Router with attribute-based routing"

# 1. Install system dependencies and Composer
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libxml2-dev \
    libpng-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 2. Install PHP extensions required by router
RUN docker-php-ext-install \
    mbstring \
    pdo_mysql \
    xml \
    bcmath

# 3. Configure PHP/opcache settings
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-settings.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/docker-php-settings.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/docker-php-settings.ini \
    && echo "session.save_path=/var/lib/php/sessions" >> /usr/local/etc/php/conf.d/docker-php-settings.ini

# 4. Enable required Apache modules
RUN a2enmod rewrite headers

# 5. Prepare working directory and install dependencies
WORKDIR /var/www/html

# Copy only composer files first to leverage Docker cache during dependency install
COPY composer.json ./
COPY . .

# Install dependencies using Composer (this runs inside the image build)
RUN composer install #--no-dev --optimize-autoloader

# 6. Create directories and copy application code
RUN mkdir -p public app/Controllers app/models src logs vendor

# Copy application source code (excluding vendor/ which is now installed)
COPY --chown=www-data:www-data . /var/www/html/

# 7. Install vhost config from a plain file
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2dissite 000-default.conf && a2ensite 000-default.conf

# 8. Create session directory and give www-data ownership
RUN mkdir -p /var/lib/php/sessions \
    && chown -R www-data:www-data /var/lib/php/sessions \
    && chmod 750 /var/lib/php/sessions

# 9. Lock down permissions and set working directory
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
