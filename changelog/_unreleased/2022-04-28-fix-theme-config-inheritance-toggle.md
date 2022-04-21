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
