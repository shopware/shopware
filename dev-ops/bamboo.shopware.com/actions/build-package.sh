#!/usr/bin/env bash

docker exec -u __USERKEY__ __APP_ID__ /usr/local/bin/wait-for-it.sh --timeout=60 mysql:3306
docker exec -u __USERKEY__ __APP_ID__ ./psh.phar bamboo:init
docker exec -u __USERKEY__ __APP_ID__ php bin/console framework:demodata --products=3000 --categories=1 --manufacturers=5 --customers=2000
docker exec -u __USERKEY__ __APP_ID__ php bin/console framework:demodata --products=3000 --categories=1 --manufacturers=5
docker exec -u __USERKEY__ __APP_ID__ php bin/console framework:demodata --products=3000 --categories=1 --manufacturers=5
docker exec -u __USERKEY__ __APP_ID__ php bin/console framework:demodata --products=3000 --categories=1 --manufacturers=5
docker exec -u __USERKEY__ __APP_ID__ php bin/console framework:demodata --products=3000 --categories=1 --manufacturers=5
docker exec -u __USERKEY__ __APP_ID__ php bin/console framework:demodata --products=3000 --categories=1 --manufacturers=5
docker exec -u __USERKEY__ __APP_ID__ php bin/console framework:demodata --products=3000 --categories=1 --manufacturers=5
docker exec -u __USERKEY__ __APP_ID__ php bin/console framework:demodata --products=3000 --categories=1 --manufacturers=5
docker exec -u __USERKEY__ __APP_ID__ php bin/console framework:demodata --products=3000 --categories=1 --manufacturers=5
docker exec -u __USERKEY__ __APP_ID__ php bin/console framework:demodata --products=3000 --categories=1 --manufacturers=5
docker exec -u __USERKEY__ __APP_ID__ ./psh.phar administration:init
docker exec -u __USERKEY__ __APP_ID__ ./psh.phar administration:build

docker exec -u __USERKEY__ __APP_ID__ mysqldump -h __DB_HOST__ -u __DB_USER__ -p__DB_PASSWORD__ __DB_NAME__ > install.sql

docker exec -u __USERKEY__ __APP_ID__ cp dev-ops/bamboo.shopware.com/templates/install.php web/install.php

docker exec -u __USERKEY__ __APP_ID__ zip -qr build/artifacts/package.zip * -x "var/cache/*" -x "var/log/*" -x "src/Administration/Resources/administration/node_modules/*"
