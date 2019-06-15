FROM composer AS composer

COPY ./composer.* /app/
WORKDIR /app
RUN composer install --no-dev --ignore-platform-reqs


FROM node AS npm

COPY ./httpdocs /app/httpdocs/
WORKDIR /app/httpdocs
RUN npm install


FROM php:7.3-apache-stretch

RUN set -ex; \
    apt-get update; \
    apt-get install -y --no-install-recommends incron rsync ssh; \
    rm -rf /var/lib/apt/lists/*

RUN set -ex; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends libjpeg-dev libpng-dev; \
    docker-php-ext-configure gd --with-jpeg-dir=/usr; \
    docker-php-ext-install gd; \
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual $savedAptMark; \
    ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
        | awk '/=>/ { print $3 }' \
        | sort -u \
        | xargs -r dpkg-query -S \
        | cut -d: -f1 \
        | sort -u \
        | xargs -rt apt-mark manual; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/*

RUN sed -ri -e 's!/var/www/html!/app/httpdocs!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!/app/httpdocs!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf && \
    echo "/app/src/main/resources/queue IN_CLOSE_WRITE,IN_NO_LOOP /app/bin/process.php" > /etc/incron.d/mvo-picture-uploader

RUN a2enmod rewrite

COPY --from=composer /app/vendor /app/vendor/
COPY --from=npm /app/httpdocs /app/httpdocs/

COPY ./bin /app/bin/
COPY ./docker/config.ini /app/src/main/resources/config.ini
COPY ./docker/entrypoint.sh /entrypoint.sh
COPY ./src /app/src/
COPY ./bootstrap.php /app/bootstrap.php

RUN chown -R www-data:www-data /albums /queue

VOLUME ["/albums", "/queue"]

ENTRYPOINT ["/entrypoint.sh"]
CMD ["frontend"]