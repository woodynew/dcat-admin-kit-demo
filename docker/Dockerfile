FROM composer:2 AS composer

FROM php:8.4-fpm-alpine AS application

RUN apk add --no-cache supervisor libzip oniguruma sqlite-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libzip-dev oniguruma-dev sqlite-dev \
    && docker-php-ext-install -j2 bcmath mbstring pcntl pdo_sqlite zip opcache \
    && apk del .build-deps

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_NAME="Dcat Admin Kit Demo" \
    APP_LOCALE=zh_CN \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync \
    MAIL_MAILER=log \
    LOG_CHANNEL=stderr \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /www

# Install locked production dependencies at build time, never during container startup.
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-autoloader --no-interaction --no-progress
COPY . .
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && php artisan vendor:publish --tag=dcat-admin-assets --tag=dcat-admin-lang \
        --tag=dcat-admin-kit-assets --tag=iframe-tab --no-interaction \
    && mkdir -p /opt/demo-storage \
    && cp -a storage/. /opt/demo-storage/ \
    && rm -rf storage \
    && ln -s /data/storage storage

COPY docker/common/php84/php/conf.d/demo.ini /usr/local/etc/php/conf.d/zz-demo.ini
COPY docker/common/php84/php-fpm.d/zz-demo.conf /usr/local/etc/php-fpm.d/zz-demo.conf
COPY docker/common/conf/supervisord.conf /etc/supervisord.conf
COPY docker/common/entrypoint.sh /usr/local/bin/demo-entrypoint
COPY docker/common/healthcheck.sh /usr/local/bin/demo-healthcheck
RUN chmod +x /usr/local/bin/demo-entrypoint /usr/local/bin/demo-healthcheck

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/demo-entrypoint"]
CMD ["supervisord", "-n", "-c", "/etc/supervisord.conf"]

FROM nginx:stable-alpine AS web
COPY docker/common/nginx/conf/default.conf /etc/nginx/conf.d/default.conf
COPY --from=application /www/public /www/public
EXPOSE 80
