FROM ghcr.io/shopware/docker-base:8.2 as base-image

COPY --chown=www-data:www-data . /var/www/html

COPY --chown=www-data:www-data .gitlab/bin/docker-entrypoint.sh /entrypoint

ENTRYPOINT [ "/entrypoint" ]
