---
title: bin/console plugin:list machine readable
issue: NEXT-10723
author: Fabian Blechschmidt
author_github: @Schrank
---
# Core
* Add option `--json` to `bin/console plugin:list`
___
# Upgrade Information

## New option for plugins
It is now possible to retriebe the plugin information in JSON format to easier parse it,
e.g. in deployment or other CI processes.
