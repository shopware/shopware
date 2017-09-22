#!/usr/bin/env bash
#DESCRIPTION: execute unit tests

./phpunit -c phpunit.xml --stop-on-failure --stop-on-error --log-junit=build/artifacts/junit.xml
