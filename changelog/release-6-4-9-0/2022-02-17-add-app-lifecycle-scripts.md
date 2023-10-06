---
title: Add app lifecycle scripts
issue: NEXT-19855
---
# Core
* Added AppLifecycleHooks in `\Shopware\Core\Framework\App\Event\Hooks`.
* Changed `\Shopware\Core\Framework\App\Lifecycle\AppLifecycle` and `\Shopware\Core\Framework\App\AppStateService` to execute the new app lifecycle hooks.
* Added `\Shopware\Core\Framework\Script\Execution\Awareness\AppSpecificHook` to mark hooks that should be only executed for specific apps.
* Changed `\Shopware\Core\Framework\Script\Execution\ScriptExecutor` to only execute scripts of a specific app for `AppSpecificHooks`. 
