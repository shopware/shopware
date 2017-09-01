#!/usr/bin/env bash

echo -n "Checking flavour... "

if [ "$(uname)" == "Darwin" ]; then
    echo "macOS detected, using brew."
    brew install nodejs
elif [ "$(expr substr $(uname -s) 1 5)" == "Linux" ]; then
    echo "Linux detected, using apt-get"
    curl -sL https://deb.nodesource.com/setup_8.x | sudo bash
    sudo apt-get install nodejs -y
fi
