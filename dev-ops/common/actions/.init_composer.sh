#!/usr/bin/env bash

composer install --no-interaction --optimize-autoloader --no-suggest

I: ln -srf vendor/bin/phpunit ./
