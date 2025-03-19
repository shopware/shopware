---
title: Fix symfony scheduler bridge for tasks with long interval
issue: NEXT-39542
author: Felix Schneider
author_github: @schneider-felix
---
# Core
* Changed `ScheduleProvider` to be stateful to ensure scheduled tasks with long
 intervals are executed even if the message worker was restarted in the meantime
* Changed `ScheduleProvider` to use a lock to ensure multiple workers can run in parallel
