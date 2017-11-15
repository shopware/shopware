#!/usr/bin/env bash

docker exec -u __USERKEY__ __APP_ID__ ./psh.phar administration:init
docker exec -u __USERKEY__ __APP_ID__ ./psh.phar administration:unit
docker exec -u __USERKEY__ __APP_ID__ sudo chown -R app-shell:app-shell .