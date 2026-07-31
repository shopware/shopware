---
title: Enforce ACL on admin action endpoints missing privilege checks
issue: #18756
---
# API
* Changed `POST /api/_action/trigger-event/{eventName}` to require the new `flow:dispatch` ACL privilege and `POST /api/_action/extension-sdk/run-action` to require the `app.all` or app-specific `app.{appName}` privilege. Requests with tokens lacking the privilege receive a `403` with `FRAMEWORK__MISSING_PRIVILEGE_ERROR`. Integrations calling these endpoints must have the respective privilege added to their ACL role.
___
# Core
* Added `Migration1785227700AddAdminActionAclPrivileges`, which grants `flow:dispatch` to existing ACL roles containing the `flow.editor` privilege, so existing admin roles keep access.
___
# Administration
* Changed the "Flow editor" privilege mapping to include `flow:dispatch`.
