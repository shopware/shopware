#!/usr/bin/env bash
#DESCRIPTION: initialization of your environment

INCLUDE: ./../../common/actions/check_requirements.sh

INCLUDE: ./../../common/actions/.init_database.sh
INCLUDE: ./../../common/actions/.init_composer.sh
