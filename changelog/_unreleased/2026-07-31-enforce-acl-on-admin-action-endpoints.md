---
title: Admin action endpoints now require ACL privileges
issue: #18756
---
# API
* Changed two admin action endpoints that previously only required authentication to enforce ACL privileges. Requests with tokens lacking the privilege receive a `403` with `FRAMEWORK__MISSING_PRIVILEGE_ERROR`:
    * `POST /api/_action/trigger-event/{eventName}` requires the new `flow:dispatch` privilege.
    * `POST /api/_action/extension-sdk/run-action` requires `app.all` or the app-specific `app.{appName}` privilege.
* Added the new `flow:dispatch` privilege to the existing "Flow editor" permission in the Administration role editor, and a migration grants it to roles that already hold that permission — existing admin users keep access without manual changes. Integrations calling these endpoints must have the respective privilege added to their ACL role.
