---
title: Message queue admin endpoints now require ACL privileges
issue: #18811
---
# API
* Changed three admin endpoints that previously only required authentication to enforce ACL privileges. Requests with tokens lacking the privilege receive a `403` with `FRAMEWORK__MISSING_PRIVILEGE_ERROR`:
    * `POST /api/_action/message-queue/consume` and `POST /api/_action/scheduled-task/run` require the new `system:queue:process` privilege.
    * `GET /api/_action/scheduled-task/min-run-interval` requires the existing `scheduled_task:read` privilege.
* Administration users are not affected: both privileges are granted to every authenticated Administration user at runtime, because these endpoints back the admin worker that processes the message queue in every admin session. Integrations and API clients calling these endpoints must have the respective privilege added to their ACL role — the runtime defaults apply to Administration users only. External workers should keep using the `bin/console messenger:consume` and `scheduled-task:run` CLI commands, which are unaffected.
