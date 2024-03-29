#!/usr/bin/env sh

set -eu
set -o pipefail

cd /var/www/html

. /usr/local/shopware/functions.sh

wait_for_mysql

if [[ -n "${TEST_WEB_INSTALLER:-}" ]]; then
    echo "Testing web installer"

    bin/ci asset:install
    rm install.lock || true
else
    # retry /setup command atmost 5 times
    for i in {1..5}; do
        if /setup 2>&1 | tee -a /tmp/setup.log; then
            break
        fi
        sleep 15
    done

    touch /var/www/html/install.lock
fi

exec /usr/bin/supervisord -c /etc/supervisord.conf