#!/usr/bin/env bash
#DESCRIPTION: initialization of your environment

INCLUDE: ./check_requirements.sh

INCLUDE: ./init-database.sh
INCLUDE: ./init-composer.sh
INCLUDE: ./init-shopware.sh

INCLUDE: ./cache.sh
