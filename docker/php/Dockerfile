FROM php:8.3-apache

# Extensions required by the app: pdo_mysql (storage), gd (thumbnail generation).
# Kept deliberately close to what OVH shared hosting provides.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
        unzip git default-mysql-client; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" gd pdo_mysql; \
    rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

# Mirror OVH's PHP-FPM defaults so we hit the same walls locally.
RUN { \
        echo 'memory_limit = 512M'; \
        echo 'max_execution_time = 165'; \
        echo 'max_input_vars = 16000'; \
        echo 'display_errors = On'; \
        echo 'error_reporting = E_ALL'; \
    } > /usr/local/etc/php/conf.d/zz-ovh-like.ini

COPY vhost.conf /etc/apache2/sites-available/000-default.conf

COPY entrypoint.sh /usr/local/bin/medias-index-entrypoint
RUN chmod +x /usr/local/bin/medias-index-entrypoint
ENTRYPOINT ["medias-index-entrypoint"]
CMD ["apache2-foreground"]

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html/app
