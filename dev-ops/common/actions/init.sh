#!/usr/bin/env bash
#DESCRIPTION: initialization of your environment

INCLUDE: ./check_requirements.sh

INCLUDE: ./.init_database.sh
INCLUDE: ./.init_composer.sh

INCLUDE: ./migrations.sh

INCLUDE: ./cache.sh
