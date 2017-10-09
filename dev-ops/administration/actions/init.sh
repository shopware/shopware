#!/usr/bin/env bash
#DESCRIPTION: init administration dependencies for docker setups

./dev-ops/administration/actions/.install_requirements.sh
npm install --prefix src/Administration/Resources/administration/
