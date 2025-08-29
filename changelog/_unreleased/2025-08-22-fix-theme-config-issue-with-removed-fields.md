---
title: Fix issue with theme config update if fields got removed
issue: 12011
---
# Storefront
* Changed the `updateTheme()` method of `Shopware\Storefront\Theme\ThemeService` to filter out non existing fields from the provided config and from existing config values in the database.