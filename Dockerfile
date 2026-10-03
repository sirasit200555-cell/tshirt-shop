FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli

RUN a2dismod mpm_event mpm_worker || true \
 && a2enmod mpm_prefork

COPY . /var/www/html/

RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -i 's/:80/:${PORT}/' /etc/apache2/sites-available/000-default.conf

CMD ["apache2-foreground"]