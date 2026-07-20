---
title: Add deprecations to the theme configuration APIs
---
# Storefront
* Added OpenAPI Schema for most of the `/api/_action/theme` endpoints.
* Deprecated `label` and `helpText` fields in the API endpoints, that were deprecated in the relevant data structures
  in the [#8027](https://github.com/shopware/shopware/pull/8027)
* Deprecated `ThemeService::getThemeConfiguration` and `ThemeService::getThemeConfigurationStructuredFields` methods.
___
# Upgrade Information

## ThemeConfiguration deprecations

The `label` and `helpText` fields in the `/api/_action/theme/{themeId}/configuration` and in the 
`/api/_action/theme/{themeId}/structured-fields` API endpoints have been deprecated. For translations you should rely on
the `labelSnippetKey` and `helpTextSnippetKey` fields instead (present only in the structured fields endpoint).

The `ThemeService::getThemeConfiguration` and `ThemeService::getThemeConfigurationStructuredFields` methods have been
deprecated in favor of the new `ThemeConfigurationService::getPlainThemeConfiguration` and
`ThemeConfigurationService::getThemeConfigurationFieldStructure` methods. The new methods return the same data as the old ones, 
excluding the deprecated fields.

