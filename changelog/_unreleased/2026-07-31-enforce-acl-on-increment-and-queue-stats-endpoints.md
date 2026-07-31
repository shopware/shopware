---
title: Enforce ACL on increment and queue-stats admin endpoints
issue: #18771
---
# API
* Changed the increment admin endpoints (`POST|GET /api/_action/increment/{pool}`, `POST /api/_action/decrement/{pool}`, `POST /api/_action/reset-increment/{pool}`) to require the new `increment:manage` ACL privilege and `GET /api/_info/queue.json` to require the `message_queue_stats:read` privilege. Integrations and API clients calling these endpoints must have the respective privilege added to their ACL role.
___
# Core
* Added `AdminApiSource::DEFAULT_USER_PRIVILEGES` (`increment:manage`, `message_queue_stats:read`), which are granted to every authenticated Administration user at runtime, as the affected endpoints back module-usage tracking and queue polling that run in every admin session. Administration users are therefore not affected by the new requirements; the runtime defaults do not apply to integrations.
