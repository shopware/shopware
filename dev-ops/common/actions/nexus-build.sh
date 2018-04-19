#!/usr/bin/env bash
#DESCRIPTION: build nexus for production and run assetic

npm run --prefix src/Nexus/Resources/nexus/ build
bin/console assets:install
