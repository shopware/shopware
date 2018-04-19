#!/usr/bin/env bash
#DESCRIPTION: initialization of shopware

bin/console framework:create:tenant --tenant-id=ffffffffffffffffffffffffffffffff

bin/console translation:import --with-plugins --tenant-id=ffffffffffffffffffffffffffffffff

bin/console plugin:update

bin/console rest:user:create admin --password=shopware --tenant-id=ffffffffffffffffffffffffffffffff

# generate default SSL private/public key
php dev-ops/generate_ssl.php