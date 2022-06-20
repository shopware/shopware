---
title: Fix Theme Config inheritance toggle
issue: NEXT-20467
author: Rafael Kraut
author_email: rk@vi-arise.com
author_github: RafaelKr
---
# Core
* Method `getThemeConfiguration` of `ThemeService` now returns the correct `baseThemeFields` instead of the same fields which are already in `currentFields`
___
# Administration
* The config inheritance toggle in the theme configuration was always enabled for all falsy values.  This is now solved by determining its state based on a dedicated `isInherited` property.
* Added optional parameter `fieldname` for method `checkInheritance` to `Storefront/Resources/app/administration/src/modules/sw-theme-manager/page/sw-theme-manager-detail/index.js`.
* Added method `handleInheritance` to `Storefront/Resources/app/administration/src/modules/sw-theme-manager/page/sw-theme-manager-detail/index.js`.
* Added attribute `customContext` to `sw-inherit-wrapper` that will be passed as additional parameter to all custom inheritance functions. 
