---
title: Increment and queue-stats admin endpoints now require ACL privileges
issue: #18771
---
# API
* Changed five admin endpoints that previously only required authentication to enforce ACL privileges. Requests with tokens lacking the privilege receive a `403` with `FRAMEWORK__MISSING_PRIVILEGE_ERROR`:
    * `POST|GET /api/_action/increment/{pool}`, `POST /api/_action/decrement/{pool}`, and `POST /api/_action/reset-increment/{pool}` require the new `increment:manage` privilege.
    * `GET /api/_info/queue.json` requires the existing `message_queue_stats:read` privilege.
* Added `increment:manage` to the runtime default privileges of every authenticated Administration user (the endpoints back module-usage tracking, which runs in every admin session); `message_queue_stats:read` already is such a default privilege, so Administration users are not affected. Integrations and API clients calling these endpoints must have the respective privilege added to their ACL role — the runtime defaults apply to Administration users only.
