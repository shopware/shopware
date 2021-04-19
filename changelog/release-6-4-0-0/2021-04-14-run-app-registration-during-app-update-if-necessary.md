---
title: Run app registration during app update if necessary
issue: NEXT-14784
---
# Core
* Changed `\Shopware\Core\Framework\App\Lifecycle\AppLifecycle::updateApp()` to run the app registration also during app updates if it is necessary.
