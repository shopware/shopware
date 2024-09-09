---
title: Deprecate legacy format in system:config:get command
issue: NEXT-38226
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Deprecated `--format=legacy` in the `system:config:get` command, the new default format will be `--format=default` which supports multiple nested config array levels
