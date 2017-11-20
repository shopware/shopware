#!/usr/bin/env bash

php dev-ops/api-generator/generate.php

INCLUDE: ./fix-cs.sh
