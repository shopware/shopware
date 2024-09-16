#!/usr/bin/env sh

set -eu
set -o pipefail

vendor/bin/shopware-deployment-helper run
