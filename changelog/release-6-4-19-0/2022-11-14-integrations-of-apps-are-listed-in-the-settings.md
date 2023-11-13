---
title: Integrations of apps are listed in the settings
issue: NEXT-21110
---
# Administration
* Changed `integrationCriteria` computed in `src/module/sw-integration/page/sw-integration-list/index.js` to remove list apps are installed.
___
# Core
*  Added a new Migration `src/Core/Migration/Migration1668677456AddAppReadPrivilegeForIntegrationRoles.php` that add `app:read` privilege into `integration.viewer` role
