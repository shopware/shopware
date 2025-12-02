---
title: Fixed bulk edit custom fields
issue: #7131
---
# Administration
* Changed `syncService` to transform `undefined` values to `null` thus preventing invalid payloads being sent, which led removing all custom fields instead of only the configured ones.

