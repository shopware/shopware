---
title: Deprecated theme translations to use admin snippets
issue: #8027
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: @Marcel Brode
---
# Storefront
* Added `labelSnippetKey` and `helpTextSnippetKey` properties to the fields to be returned with `ThemeService::getThemeConfigurationStructuredFields`
* Removed `label` and `helpText` translations from our base theme's `theme.json` in favor of `labelSnippetKey` and `helpTextSnippetKey`
* Deprecated `label` and `helpText` translations in `theme.json`, use snippet keys instead

___

# Upgrade Information

## Translation labels and helpTexts for Themes

A constructed snippet key was introduced in Shopware 6.7 and will be required starting 6.8.
This affects `label` and `helpText` properties in the `theme.json`, which are used in the theme manager.
To provide translations for theme configuration, [creating administration snippets as usual](https://developer.shopware.com/resources/admin-extension-sdk/faq/#how-can-i-use-snippets-to-translate-my-app)
will be mandatory.

The snippet keys to be used are constructed as follows.
The mentioned `themeName` implies the `technicalName` property of the theme in kebab case.
Also, please notice that unnamed tabs, blocks or sections will be accessible via `default`.

Examples:
* Tab: `sw-theme.<technicalName>.<tabName>.label`
  * e.g.: `sw-theme.swag-shape-theme.colorTab.label`
* Block: `sw-theme.<technicalName>.<tabName>.<blockName>.label`
  * e.g.: `sw-theme.swag-shape-theme.colorTab.primaryColorsBlock.label`
* Section: `sw-theme.<technicalName>.<tabName>.<blockName>.<sectionName>.label`
  * e.g.: `sw-theme.swag-shape-theme.colorTab.primaryColorsBlock.homeSection.label`
* Field:
  * `sw-theme.<technicalName>.<tabName>.<blockName>.<sectionName>.<fieldName>.label`
    * e.g.: `sw-theme.swag-shape-theme.colorTab.primaryColorsBlock.homeSection.sw-color-primary-dark.label`
  * `sw-theme.<technicalName>.<tabName>.<blockName>.<sectionName>.<fieldName>.helpText`
    * e.g.: `sw-theme.swag-shape-theme.colorTab.primaryColorsBlock.homeSection.sw-color-primary-dark.helpText`
* Options: `sw-theme.<technicalName>.<tabName>.<blockName>.<sectionName>.<fieldName>.<index>.label`
  * e.g.: `sw-theme.swag-shape-theme.colorTab.primaryColorsBlock.homeSection.sw-color-primary-dark.0.label`
