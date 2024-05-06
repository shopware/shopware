---
title: Fix theme config label inheritance
issue: NEXT-29093
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Storefront
* Changed `getConfigInheritance` method of `ThemeService` to add default `@Storefront` inheritance in case nothing else is configured.
* Changed `getTranslations` method of `ThemeService` to work for themes without a `parentThemeId` to get translations of the default Storefront theme.
