FROM composer AS composer

COPY ./composer.* /app/
WORKDIR /app
RUN composer install --no-dev --ignore-platform-reqs


FROM node:15 AS npm

COPY ./httpdocs /app/httpdocs/
WORKDIR /app/httpdocs
RUN npm install


FROM ghcr.io/programie/dockerimages/php

RUN set -ex; \
    apt-get update; \
    apt-get install -y --no-install-recommends incron rsync ssh; \
    rm -rf /var/lib/apt/lists/*; \
    install-php 8.1 gd; \
    sed -ri -e 's!/var/www/html!/app/httpdocs!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!/app/httpdocs!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf && \
    echo "/queue IN_CLOSE_WRITE,IN_NO_LOOP /app/bin/process.php" > /etc/incron.d/mvo-picture-uploader && \
    a2enmod rewrite

COPY --from=composer /app/vendor /app/vendor/
COPY --from=npm /app/httpdocs /app/httpdocs/

COPY ./bin /app/bin/
COPY ./docker/config.ini /app/src/main/resources/config.ini
COPY ./docker/entrypoint.sh /entrypoint.sh
COPY ./src /app/src/
COPY ./bootstrap.php /app/bootstrap.php

VOLUME ["/albums", "/queue", "/pictures-cache"]

ENTRYPOINT ["/entrypoint.sh"]
CMD ["frontend"]