#!/usr/bin/env bash
#DESCRIPTION: starting administration dev server for hot module reloading

bin/console administration:dump:plugins
npm run --prefix src/Administration/Resources/administration/ dev -- __SW_HOST____SW_BASE_PATH__
