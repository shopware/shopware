#!/usr/bin/env sh

set -eu

cd /var/www/html

if [[ -n "${TEST_WEB_INSTALLER:-}" ]]; then
    echo "Testing web installer"

    bin/ci asset:install
    rm var/install.lock install.lock || true
else
    /setup
fi

exec /usr/bin/supervisord -c /etc/supervisord.conf