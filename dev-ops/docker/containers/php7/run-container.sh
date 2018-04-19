#!/usr/bin/env bash

chmod -R 0600 /home/app-shell/.ssh/*
chown -R app-shell:app-shell /home/app-shell/.ssh/*

eval "$(ssh-agent -s)"
ssh-add /home/app-shell/.ssh/id_rsa

apache2-foreground