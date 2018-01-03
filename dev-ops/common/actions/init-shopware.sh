#!/usr/bin/env bash
#DESCRIPTION: initialization of shopware

bin/console translation:import --with-plugins

bin/console category:build:path

bin/console seo:url:generate -v

bin/console dbal:refresh:index -v

bin/console plugin:update

bin/console assetic:dump

bin/console rest:user:create admin --password=shopware

# generate default SSL private/public key
php dev-ops/generate_ssl.php