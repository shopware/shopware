#!/usr/bin/env bash
#DESCRIPTION: clears all caches

bin/console cache:clear --no-warmup --no-optional-warmers
bin/console cache:warmup
