#!/usr/bin/env bash

groupadd -g 1000 app-shell
useradd -s /bin/bash -m -u 1000 -g 1000 app-shell
mkdir -p /home/app-shell/.ssh
chown -R app-shell:app-shell /home/app-shell
echo -e "app-shell\napp-shell\n" | passwd app-shell
echo 'app-shell  ALL=(ALL:ALL) NOPASSWD: ALL' >> /etc/sudoers
