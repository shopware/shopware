#!/usr/bin/env bash

printf "\nDocker containers status:"
docker ps --format "table{{.ID}}\t{{.Names}}\t{{.Status}}"
printf "\nNetwork status:"
docker network ls