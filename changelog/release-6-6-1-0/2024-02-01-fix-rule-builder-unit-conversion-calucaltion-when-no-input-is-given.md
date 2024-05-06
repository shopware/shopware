---
title: fix rule builder unit conversion calculation when no input is given
issue: NEXT-33461
author: Lars Kemper
author_email: l.kemper@shopware.com
author_github: @LarsKemper
---
# Administration
* Changed `onUnitChange` method in `sw-condition-unit-menu` to prevent `NaN` values when no input is given.
