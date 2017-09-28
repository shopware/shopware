#!/usr/bin/env bash

php dev-ops/read-generator/Generate.php

php dev-ops/generate_writer.php

INCLUDE: ./fix-cs.sh
