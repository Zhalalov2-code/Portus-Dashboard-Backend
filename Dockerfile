FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y cron unzip git \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql

RUN a2enmod rewrite headers

# Composer (для зависимостей API-публикации и WebSocket-сервера)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/crontab /etc/cron.d/portusapp
RUN chmod 0644 /etc/cron.d/portusapp

WORKDIR /var/www/html

COPY . .

# Устанавливаем зависимости (Ratchet, Predis) в vendor/
RUN composer install --no-dev --no-interaction --optimize-autoloader

RUN mkdir -p api/uploads/chassi api/uploads/lkw logs \
    && chown -R www-data:www-data api/uploads logs \
    && chmod -R 775 api/uploads logs

EXPOSE 80

CMD ["apache2-foreground"]
