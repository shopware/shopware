---
title: Fix double triggering of switch field `update:value` event
issue: #7697
---
# Administration
* Changed `sw-switch-field` to not pass `update:value` event listeners further down, thus preventing emitting the same event twice, which lead to errors in the permission UI.
