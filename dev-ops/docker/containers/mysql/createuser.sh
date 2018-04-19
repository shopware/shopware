#!/usr/bin/env bash

groupadd -g 1000 app-mysql
useradd -s /bin/bash -m -u 1000 -g 1000 app-mysql
mkdir -p /home/app-mysql/.ssh
chown -R app-mysql:app-mysql /home/app-mysql
echo -e "app-mysql\napp-mysql\n" | passwd app-mysql
echo 'app-mysql  ALL=(ALL:ALL) ALL' >> /etc/sudoers
