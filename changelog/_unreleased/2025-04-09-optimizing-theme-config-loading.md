---
title: Optimizing theme config loading
issue: https://github.com/shopware/shopware/issues/7766
---
# Core
* Changed `\Shopware\Core\System\Snippet\SnippetService` to use `\Shopware\Storefront\Theme\ThemeRuntimeConfigService` to get information about available themes
___
# Storefront
* Added new `\Shopware\Storefront\Theme\ThemeRuntimeConfig` entity and `theme_runtime_config` table to store theme runtime configuration
* Added `\Shopware\Storefront\Theme\ThemeRuntimeConfigService` to handle theme runtime configurations
* Changed `\Shopware\Storefront\Theme\ThemeLifecycleService`, adding optional `$configurationCollection` parameter to the `refreshTheme` method, and deprecating class to be marked as final in the next major version.
* Changed theme configuration loading in the code, used during storefront rendering, to use the new `\Shopware\Storefront\Theme\ThemeRuntimeConfigService`:
  * `\Shopware\Storefront\Theme\ResolvedConfigLoader`
  * `\Shopware\Storefront\Theme\ThemeScripts`
  * `\Shopware\Storefront\Theme\ThemeInheritanceBuilder`
  * `\Shopware\Storefront\Framework\Routing\TemplateDataSubscriber`
* Deprecated `\Shopware\Storefront\Theme\CachedResolvedConfigLoader`, as it is no longer used in the storefront
___
# Upgrade Information
## Theme configuration changes
* Theme configuration used during storefront rendering are now stored in a `theme_runtime_config` table and regenerated on the refresh stage of theme lifecycle.
* The `\Shopware\Storefront\Theme\CachedResolvedConfigLoader` is now deprecated and will be removed in the next major version. Please update the code that directly uses it to use the `\Shopware\Storefront\Theme\ResolvedConfigLoader` instead.
___
# Next Major Version Changes
## Removal of CachedResolvedConfigLoader
* The `\Shopware\Storefront\Theme\CachedResolvedConfigLoader` was removed.

## Changes to ThemeLifecycleService
* The `\Shopware\Storefront\Theme\ThemeLifecycleService` became final.
* The new optional parameter `$configurationCollection` was added to the `refreshTheme` method.
