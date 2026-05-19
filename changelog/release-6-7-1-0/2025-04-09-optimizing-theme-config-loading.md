---
title: Optimizing theme config loading
issue: https://github.com/shopware/shopware/issues/7766
---
# Core
* Added `\Shopware\Core\System\Snippet\Event\SnippetsThemeResolveEvent` to remove dependency on `Storefront` components from `SnippetService`
* Changed `\Shopware\Core\System\Snippet\SnippetService`:
  * Used/non-used themes information is retrieved using `SnippetsThemeResolveEvent`
  * Deprecated `getUnusedThemes` method, replacement will not be provided.
___
# Storefront
* Added new `\Shopware\Storefront\Theme\ThemeRuntimeConfig` entity and `theme_runtime_config` table to store theme runtime configuration
* Added `\Shopware\Storefront\Theme\ThemeRuntimeConfigService` to handle theme runtime configurations
* Added `\Shopware\Storefront\Theme\Subscriber\ThemeSnippetsSubscriber` to collect information about active/non-active themes for snippets functionality
* Changed `\Shopware\Storefront\Theme\ThemeLifecycleService`, adding optional `$configurationCollection` parameter to the `refreshTheme` method
* Deprecated `\Shopware\Storefront\Theme\ThemeLifecycleService` to be marked as final in the next major version.
* Changed theme configuration loading in the code, used during storefront rendering, to use the new `\Shopware\Storefront\Theme\ThemeRuntimeConfigService`:
  * `\Shopware\Storefront\Theme\ResolvedConfigLoader`
  * `\Shopware\Storefront\Theme\ThemeScripts`
  * `\Shopware\Storefront\Theme\ThemeInheritanceBuilder`
  * `\Shopware\Storefront\Framework\Routing\TemplateDataSubscriber`
* Changed `\Shopware\Storefront\Theme\CachedResolvedConfigLoaderInvalidator` name to `\Shopware\Storefront\Theme\ThemeConfigCacheInvalidator`
* Deprecated `\Shopware\Storefront\Theme\CachedResolvedConfigLoader`, as it is no longer used in the storefront
* Deprecated `\Shopware\Storefront\Theme\Exception\ThemeAssignmentException`
___
# Upgrade Information

## Theme configuration changes
* Theme configuration used during storefront rendering is now stored in a `theme_runtime_config` table and regenerated on the refresh stage of theme lifecycle.
* The `\Shopware\Storefront\Theme\CachedResolvedConfigLoader` is now deprecated and will be removed in the next major version. Please update the code that directly uses it to use the `\Shopware\Storefront\Theme\ResolvedConfigLoader` instead.
* The `\Shopware\Storefront\Theme\Exception\ThemeAssignmentException` is now deprecated and will be removed in the next major version. Please use `\Shopware\Storefront\Theme\Exception\ThemeException::themeAssignmentException`.
