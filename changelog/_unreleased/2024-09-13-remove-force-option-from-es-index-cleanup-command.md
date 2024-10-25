---
title: Remove force option from es:index:cleanup command
author: Marcus Müller
author_email: 25648755+M-arcus@users.noreply.github.com
author_github: @M-arcus
issue: NEXT-00000
---

# Core
* Removed `--force` option from command `es:index:cleanup` due to redundancy with `--no-interaction` option
* Changed `es:index:cleanup` command return code to successful when canceled
* Changed `es:reset`, `es:admin:reset` and `es:reset` confirmation to use `confirm` method instead of `ask` method
