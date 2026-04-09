---
title: Load all hookable entities dynamically by checking shopware.entity.hookable tag
---
# Core
* Added new service tag `shopware.entity.hookable` in `Shopware\Core\Framework\DependencyInjection\CompilerPass\AutoconfigureCompilerPass`
* Added new interface `Shopware\Core\Framework\Webhook\Hookable\HookableEntityInterface`
* Changed `Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector::getHookableEntities` to load hookable entities dynamically
