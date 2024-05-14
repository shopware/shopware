ARG SHOPWARE_CORE_VERSION

FROM ghcr.io/shopware/docker-base:8.2 as base-image


FROM composer:2.7 as build-installer

ARG COMPOSER_CACHE_DIR=/tmp/cache

COPY . /app

RUN echo ${COMPOSER_CACHE_DIR} &&  ls -las ${COMPOSER_CACHE_DIR} \
    && git config --global --add safe.directory /app \
    && cd /app/src/WebInstaller \
    && composer config platform.php 8.2 \
    && COMPOSER_CACHE_DIR=${COMPOSER_CACHE_DIR} composer install \
    && composer build-phar

FROM composer:2.7 as build

ARG SHOPWARE_CORE_VERSION
ARG COMPOSER_CACHE_DIR=/tmp/cache

RUN --mount=type=secret,id=composer_auth,dst=/src/auth.json \
    --mount=type=cache,target=/root/.composer \
    --mount=type=cache,target=/root/.npm \
    echo ${COMPOSER_CACHE_DIR} &&  ls -las ${COMPOSER_CACHE_DIR} \
    && git clone https://github.com/shopware/production.git . \
    && chown -R www-data:www-data . \
    && composer config minimum-stability dev \
    && composer config prefer-stable true \
    && composer config platform.php 8.2 \
    && COMPOSER_CACHE_DIR=${COMPOSER_CACHE_DIR} composer require --ignore-platform-reqs --no-interaction "shopware/core:${SHOPWARE_CORE_VERSION}"

ADD --chown=www-data:www-data context.tar.gz /app/custom/platform

RUN (mkdir -p /app/custom/packages && cd /app/custom/packages && ls ../platform/src | xargs -n 1 -I {} ln -s ../platform/src/{})

FROM base-image

USER root

RUN apk add git --no-cache

USER www-data

COPY --chown=www-data:www-data .gitlab/docker/entrypoint.sh /entrypoint

COPY --from=build --link --chown=www-data:www-data /app /var/www/html

COPY --from=build-installer --link --chown=www-data:www-data /app/src/WebInstaller/shopware-installer.phar.php /var/www/html/public/shopware-installer.phar.php

ENTRYPOINT [ "/entrypoint" ]
