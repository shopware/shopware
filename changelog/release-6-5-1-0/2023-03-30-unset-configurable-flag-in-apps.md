---
title: Reset the `configurable` flag in apps when the `config.xml` was removed in an update
issue: NEXT-26048
---
# Core
* Changed `\Shopware\Core\Framework\App\Lifecycle\AppLifecycle` to reset the `configurable` flag to false when the `config.xml` file was removed in an app update.
