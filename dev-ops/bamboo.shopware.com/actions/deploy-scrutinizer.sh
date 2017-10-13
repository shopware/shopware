#!/usr/bin/env bash


curl -k -L -O  "https://scrutinizer-ci.com/ocular.phar"
chmod +x ocular.phar
./ocular.phar code-coverage:upload --access-token="__OCULAR_ACCESS_TOKEN__" --repository="g/shopware/shopware/" --parent="__PARENT_REVISION__" --revision="__REVISION__" --format=php-clover build/artifacts/coverage.clover
rm ocular.phar
