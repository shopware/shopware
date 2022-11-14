---
title: Check APP_URL configuration
issue: NEXT-9559
---
# Core
* Added `\Shopware\Core\Maintenance\System\Service\AppUrlVerifier` to check whether the shop is reachable under the configured `APP_URL`.
* Changed route `/api/_info/config` to include information if the `APP_URL` is configured correctly.
___
# Administration
* Added component `sw-app-wrong-app-url-modal` to show a warning if the `APP_URL` is not set up correctly.
* Changed `sw-extension-my-extensions-listing` to show a warning if the `APP_URL` is not set up correctly.
