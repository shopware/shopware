---
title: Scheduled tasks stuck in queued despite never dispatched
issue: NEXT-20061
author: Benny Poensgen
author_email: poensgen@vanwittlaer.de
author_github: vanWittlaer
---

# Core

* Fix logic in scheduled-task:run command in a way that only the task failed to dispatch is set to "queued" whilst all other tasks not yet dispatched will remain "scheduled".
