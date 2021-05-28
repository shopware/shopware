---
title: Add-additional-check-for-stale-app-url-changes
issue: NEXT-15488

 
---
# Core
*  The controller for the route `api/app-system/app-url-change/url-difference` now checks if the `APP_url` is still outdated and if so deletes the system config value indicating otherwise
