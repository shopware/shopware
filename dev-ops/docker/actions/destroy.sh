#!/usr/bin/env bash

echo "Removing all docker container caches:"
docker-compose kill
docker rm $(docker ps -a -q)
docker rmi --force $(docker images -q)