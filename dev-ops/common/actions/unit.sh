#!/usr/bin/env bash
#DESCRIPTION: execute unit tests

php vendor/bin/phpunit --stop-on-failure --stop-on-error --log-junit=build/artifacts/junit.xml
