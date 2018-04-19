#!/usr/bin/env bash
#DESCRIPTION: init nexus dependencies for docker setups

curl -sL https://deb.nodesource.com/setup_8.x | sudo bash
sudo apt-get install nodejs -y
npm install --prefix src/Nexus/Resources/nexus/
