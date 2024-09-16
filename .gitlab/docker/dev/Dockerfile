FROM ghcr.io/shopware/docker-base:8.2 AS base-image

FROM composer:2.7 AS build

ARG COMPOSER_CACHE_DIR=/tmp/cache

RUN git clone https://github.com/shopware/production.git . \
    && chown -R www-data:www-data .

COPY --chown=www-data:www-data . /app/custom/platform

RUN echo ${COMPOSER_CACHE_DIR} &&  ls -las ${COMPOSER_CACHE_DIR} \
    && composer config -d /app/custom/platform/src/Core version 6.5.9999999.9999999-dev \
    && composer config repositories.custom-packages '{"type": "path", "url": "custom/packages/*", "options": { "symlink": false } }' \
    && composer config minimum-stability dev \
    && composer config prefer-stable true \
    && (mkdir -p /app/custom/packages && cd /app/custom/packages && ls ../platform/src | xargs -n 1 -I {} ln -s ../platform/src/{}) \
    && COMPOSER_CACHE_DIR=${COMPOSER_CACHE_DIR} composer require --ignore-platform-reqs --no-interaction "shopware/core:*"

RUN COMPOSER_CACHE_DIR=${COMPOSER_CACHE_DIR} composer require --ignore-platform-reqs --no-interaction "shopware/deployment-helper"

FROM base-image

COPY --chown=82:82 .gitlab/docker/init.sh /init.sh
COPY --chown=82:82 .gitlab/docker/.shopware-project.yml /var/www/html/.shopware-project.yml

COPY --from=build --link --chown=82:82 /app /var/www/html
