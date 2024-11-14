---
title: Add SCSS Validator for theme changes
issue: NEXT-21038
---
# Storefront
* Added new class `\Shopware\Storefront\Theme\Validator\SCSSValidator` with static maethod `validate` to validate theme field changes.
* Added parameters `AbstractScssCompiler` and array `$customAllowedRegex` to `Shopware\Storefront\Theme\Controller\ThemeController`.
* Added parameter array `$customAllowedRegex` to `Shopware\Storefront\Theme\ThemeCompiler`.
* Added new route `/api/_action/theme/validate-fields` to `Shopware\Storefront\Theme\Controller\ThemeController` to validate theme field changes.
* Changed `Shopware\Storefront\Theme\ThemeCompiler::compileTheme`to sanitize scss values before compiling.
* Added new storefront configuration `storefront.theme.allowed_scss_values` as an array of regular expressions to allow custom scss values.
___
# Upgrade Information

## SCSS Values will be validated and sanitized
From now on, every scss value added by a theme will be validated when changed in the administration interface.
The values will be sanitized when they are invalid to a standard value when they are not valid when changed before or via api.
