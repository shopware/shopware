#!/usr/bin/env bash

docker exec __APP_ID__ /tmp/wait.sh
docker exec -u __USERKEY__ __APP_ID__ ./psh.phar bamboo:init
docker exec -u __USERKEY__ __APP_ID__ ./psh.phar bamboo:unit
docker exec -u __USERKEY__ __APP_ID__ ./psh.phar bamboo:deploy-scrutinizer
docker exec -u __USERKEY__ __APP_ID__ sudo chown -R app-shell:app-shell .