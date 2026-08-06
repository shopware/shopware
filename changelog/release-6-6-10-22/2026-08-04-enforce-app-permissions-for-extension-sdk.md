---
title: Enforce app permissions for Extension SDK requests and Administration modules
issue: '#18964'
---
# API
* Changed Extension SDK action and URI-signing requests to require the `app.all` or `app.{appName}` privilege for the selected app. Target URLs must be absolute and use a host declared in the app manifest's `allowed-hosts`.
___
# Administration
* Changed Administration module responses to omit modules for apps the current user cannot access.
