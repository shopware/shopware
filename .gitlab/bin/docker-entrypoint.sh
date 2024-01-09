#!/usr/bin/env sh

set -eu

cd /var/www/html

/setup

exec /usr/bin/supervisord -c /etc/supervisord.conf