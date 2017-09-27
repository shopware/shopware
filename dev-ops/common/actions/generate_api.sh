#!/usr/bin/env bash

php dev-ops/generate_writer.php

php dev-ops/read-generator/Generate.php

./vendor/bin/php-cs-fixer fix -v src
