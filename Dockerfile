FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y cron \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql

RUN a2enmod rewrite headers

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/crontab /etc/cron.d/portusapp
RUN chmod 0644 /etc/cron.d/portusapp

WORKDIR /var/www/html

COPY . .

RUN mkdir -p api/uploads/chassi api/uploads/lkw logs \
    && chown -R www-data:www-data api/uploads logs \
    && chmod -R 775 api/uploads logs

EXPOSE 80

CMD ["apache2-foreground"]
