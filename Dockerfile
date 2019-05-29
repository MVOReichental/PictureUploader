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

COPY . /app
RUN mv /app/docker/config.ini /app/src/main/resources/config.ini
RUN chown -R www-data:www-data /app

VOLUME ["/app/src/main/resources/queue", "/tmp/pictures-cache"]

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["frontend"]