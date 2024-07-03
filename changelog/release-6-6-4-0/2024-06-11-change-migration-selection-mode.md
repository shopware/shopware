---
title: Change migration selection mode
issue: NEXT-33734
author: Michael Telgmann
author_github: mitelg
---
# Core
* Added new option `version-selection-mode` to the `system:update:finish` command.
* Changed the version selection mode to the default value "safe" of the called `database:migrate-destructive` command within the `system:update:finish` command.
