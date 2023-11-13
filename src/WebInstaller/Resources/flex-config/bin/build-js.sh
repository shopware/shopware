#!/usr/bin/env bash

BIN_DIR="$(cd -P -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"

set -euo pipefail


if [[ -e "${BIN_DIR}/build-administration.sh" ]]; then
  "${BIN_DIR}/build-administration.sh"
fi

if [[ -e "${BIN_DIR}/build-storefront.sh" ]]; then
  "${BIN_DIR}/build-storefront.sh"
fi