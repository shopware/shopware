#!/usr/bin/env bash

echo "COMPOSE_PROJECT_NAME: ${COMPOSE_PROJECT_NAME}"

cp dev-ops/docker/docker-compose.override.yml .
dev-ops/docker/containers/scriptcreator.sh __USER_ID__ __GROUP_ID__

docker-compose build && docker-compose up -d
wait

echo "All containers started successfully"
echo "Web server IP: http://10.101.101.56"
