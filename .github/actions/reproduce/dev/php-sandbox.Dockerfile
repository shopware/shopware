# Sandbox image for the direct (PHPUnit) executor. The agent-authored integration test is untrusted
# and the trusted verify runs it host-side, so instead run it in this egress-locked container: a plain
# PHP CLI plus the extensions Shopware's kernel boot needs. Only PHP + extensions come from the image;
# the provisioned shop is bind-mounted in and the DB is reached over the host. Built at verify time by
# the "Arm direct sandbox" workflow step (this is not on any hot path). Keep the PHP major/minor in
# step with the provisioned php-version.
FROM php:8.4-cli
RUN curl -sSLf https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_mysql intl gd zip sodium bcmath mbstring opcache pcntl
