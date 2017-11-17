#!/usr/bin/env bash

echo "COMPOSE_PROJECT_NAME: ${COMPOSE_PROJECT_NAME}"

docker-compose down --volumes --remove-orphans
docker-compose rm --force -v
echo "All containers stopped"