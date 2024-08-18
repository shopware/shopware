---
title: Deprecate sw-dashboard-statistics
issue: NEXT-36326
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Administration
* Deprecated component `sw-dashboard-statistics` which will be removed without replacement.
* Deprecated component section `sw-chart-card__before`. Use `sw-dashboard__before-content` instead.
* Deprecated component section `sw-chart-card__after`. Use `sw-dashboard__after-content` instead.
* Deprecated data set `sw-dashboard-detail__todayOrderData` which will be removed without replacement. Use Admin API instead.
* Deprecated data set `sw-dashboard-detail__statisticDateRanges` which will be removed without replacement. Use Admin API instead.
* Added component section `sw-dashboard__after-content` in `src/module/sw-dashboard/page/sw-dashboard-index/sw-dashboard-index.html.twig`
___
# Next Major Version Changes
## Removal of sw-dashboard-statistics and associated component sections and data sets
The component `sw-dashboard-statistics` (`src/module/sw-dashboard/component/sw-dashboard-statistics`) has been removed without replacement.

The associated component sections `sw-chart-card__before` and `sw-chart-card__after` were removed, too.
Use `sw-dashboard__before-content` and `sw-dashboard__after-content` instead.

Before:
```js
import { ui } from '@shopware-ag/meteor-admin-sdk';

ui.componentSection.add({
    positionId: 'sw-chart-card__before',
    ...
})
```

After:
```js
import { ui } from '@shopware-ag/meteor-admin-sdk';

ui.componentSection.add({
    positionId: 'sw-dashboard__before-content',
    ...
})
```

Additionally, the associated data sets `sw-dashboard-detail__todayOrderData` and `sw-dashboard-detail__statisticDateRanges` were removed.
In both cases, use the Admin API instead.
