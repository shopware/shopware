---
title: Enforce app permissions for Extension SDK requests and Administration modules
issue: '#18964'
---
# API
* Extension SDK action and URI-signing requests now require the `app.all` or `app.{appName}` privilege for the selected app. Target URLs must be absolute and use a host declared in the app manifest's `allowed-hosts`.

# Administration
* Administration modules are no longer returned for apps the current user cannot access.
