---
title: Only load active app scripts
issue: NEXT-21370
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed `ScriptLoader` to load the active state of the scripts and skip these disabled scripts in `ScriptExecutor`
