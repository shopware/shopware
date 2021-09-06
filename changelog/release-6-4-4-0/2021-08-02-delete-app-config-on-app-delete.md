---
title: Delete app configuration when the app is deleted
issue: NEXT-16377
---
# Core
* Changes `\Shopware\Core\Framework\App\Lifecycle\AppLifecycle` to remove app configuration, when the app gets deleted and the user data should not be kept.
