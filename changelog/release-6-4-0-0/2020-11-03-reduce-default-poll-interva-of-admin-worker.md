---
title: Reduce default poll-intervall of admin-worker
issue: NEXT-12746
author: Manuel Kress
author_email: 6232639+windaishi@users.noreply.github.com
---
# Core
* Changed the default `poll_interval` of the`admin_worker` from 30 to 20 seconds.
  * This should stop the `consume`-request from failing with timeouts when the `max_execution_time` of PHP is set to 30 seconds (default).
