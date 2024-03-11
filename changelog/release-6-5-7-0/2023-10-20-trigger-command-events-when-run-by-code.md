---
title: Trigger Symfony command events, when commands are executed via other commands
issue: NEXT-31265
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed sub command invocation within `system:install`, `system:setup` and `system:update:finish` to use the console application so that the correct events are dispatched 
