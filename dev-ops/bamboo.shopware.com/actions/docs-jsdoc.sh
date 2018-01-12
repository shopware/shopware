#!/usr/bin/env bash

docker exec -u __USERKEY__ __APP_ID__ ./psh.phar administration:init
docker exec -u __USERKEY__ __APP_ID__ ./psh.phar administration:generate-api-docs