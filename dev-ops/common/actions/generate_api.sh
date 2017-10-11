#!/usr/bin/env bash

php dev-ops/read-generator/Generate.php

php dev-ops/write-generator/generate.php

INCLUDE: ./fix-cs.sh
