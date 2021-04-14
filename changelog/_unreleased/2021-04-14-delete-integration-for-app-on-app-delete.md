---
title: Delete integration for app when the app gets deleted
issue: NEXT-14790
---
# Core
* Changed `\Shopware\Core\Framework\App\Lifecycle\AppLifecycle::removeAppAndRole()` to additionally remove the integration of the app.
