---
title: Add validation for theme configuration
issue: #10679
---
# Storefront
* Added new method `validateThemeConfig` to `\Shopware\Storefront\Theme\ThemeService` for validating theme configuration changes.
* Added new boolean parameter `validate` to API route `/api/_action/theme/{themeId}` in `\Shopware\Storefront\Theme\Controller\ThemeController` which will activate the config validation on theme update.
* Added new boolean parameter `sanitize` to API route `/api/_action/theme/{themeId}` in `\Shopware\Storefront\Theme\Controller\ThemeController` which will activate config sanitization during validation. Only applies if `validate` is set.
___
# Administration
* Changed the theme manager module to trigger theme config validation on theme update. Reported errors will be mapped to the corresponding form fields to show the user which fields are invalid.