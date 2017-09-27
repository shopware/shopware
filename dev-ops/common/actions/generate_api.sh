#!/usr/bin/env bash

php dev-ops/read-generator/Generate.php

php dev-ops/generate_writer.php

./vendor/bin/php-cs-fixer fix -v src
