---
title: Fix height of sdk modules
issue: NEXT-11111
author: Heiner Lohaus
author_email: heiner@lohaus.eu
author_github: hlohaus
---
# Administration
* Fix height of iframes in skd modules. Sets iframe container height to 100% and make it overflow hidden.
* Fix the error page of sdk modules and use now the `load` event to detect module loads.
* Fix issue with multiple modules in one app. Watchs now the locationId for changes.