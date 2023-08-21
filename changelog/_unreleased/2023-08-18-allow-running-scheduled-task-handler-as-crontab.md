---
title: Allow running scheduled task handler as crontab
issue: NEXT-29980
---

# Core

* Added new parameter `--no-wait` to `scheduled-task:run` command to run scheduled tasks without waiting actively. 
  * With the flag active, the command can be used in normal crontab and doesn't need to run in the background.
