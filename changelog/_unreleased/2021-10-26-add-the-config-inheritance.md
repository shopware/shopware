---
title: Add theme config inheritance
issue: NEXT-17637
flag: FEATURE_NEXT_17637
---
# Administration
* Added `currentThemeConfig` prop to `/src/modules/sw-theme-manager/page/sw-theme-manager-detail/index.js` to hold the 
configuration of the current theme without the inheritances.
* Added `sw-inherit-wrapper` to all config fields in `src/modules/sw-theme-manager/page/sw-theme-manager-detail/sw-theme-manager-detail.html.twig`.
* Changed snippet for `sw-theme-manager.detail.inheritanceInfo`.

___
# Storefront
* Added `configInheritance` property to `src/Storefront/Theme/StorefrontPluginConfiguration/StorefrontPluginConfiguration.php`
* Changed `\Shopware\Storefront\Theme\ThemeLifecycleService::refreshTheme` to add the parentId to a new activated theme 
if the `configInheritance` configuration is set in the `theme.json`.
* Changed `\Shopware\Storefront\Theme\ThemeService::getThemeConfiguration` by adding two new keys to the return array
  * `currentFields` holding only the values configured in the current theme. Not configured fields are set to null.
  * `baseThemeFields` holding only the values configured in parent themes. Not configured fields are set to null.
