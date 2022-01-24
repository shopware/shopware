---
title: bin/console plugin:list machine readable
issue: NEXT-19100
author: Fabian Blechschmidt
author_github: @Schrank
---
# Core
* Add option `--json` to `bin/console plugin:list`
___
# Upgrade Information

## New `--json` option for plugin list command
It is now possible to retrieve the plugin information in JSON format to easier parse it,
e.g. in deployment or other CI processes.
