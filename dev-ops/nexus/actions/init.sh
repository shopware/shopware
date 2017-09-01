#!/usr/bin/env bash
#DESCRIPTION: init nexus dependencies for docker setups

./dev-ops/nexus/actions/.install_requirements.sh
npm install --prefix src/Nexus/Resources/nexus/
