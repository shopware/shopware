---
title: Fix DisableExtensionsCompilerPass
issue: NEXT-21579
---
# Core
* Changed `\Shopware\Core\Framework\DependencyInjection\CompilerPass\DisableExtensionsCompilerPass` to correctly override `ActiveAppsLoader`-service if `DISABLE_EXTENSIONS` is set.
* Changed `\Shopware\Core\Framework\Framework` to register `DisableExtensionsCompilerPass` as compiler pass.
