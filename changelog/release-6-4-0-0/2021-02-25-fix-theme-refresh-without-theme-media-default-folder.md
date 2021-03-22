---
title: Fix theme:refresh without theme media default folder
issue: NEXT-13952
---
# Storefront
* Changed method `createMediaStruct()` in `Shopware\Storefront\Theme\ThemeLifecycleService` to accept `null` as `$themeFolderId`-param, thus using the root media folder as fallback when there is no default folder for theme files configured.
