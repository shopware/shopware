---
title: Added date range selection to dashboard statistics
issue: NEXT-17639
author: Eric Heinzl
author_email: e.heinzl@shopware.com 
---
# Administration
* Added date range selection to dashboard statistics in `src/Administration/Resources/app/administration/src/module/sw-dashboard/page/sw-dashboard-index`
* Added fillEmptyValues prop in `src/Administration/Resources/app/administration/src/app/component/base/sw-chart/index.js`. You can now also define your time unit _(day/hour/minute)_
  * Added possibility to fill in zero values for hours and minutes _(before only days were available)_
* Deprecated computed `orderCountMonthSeries` in `src/Administration/Resources/app/administration/src/module/sw-dashboard/page/sw-dashboard-index/index.js`. Please use `orderCountSeries` instead
* Deprecated computed `orderSumMonthSeries` in `src/Administration/Resources/app/administration/src/module/sw-dashboard/page/sw-dashboard-index/index.js`. Please use `orderSumSeries` instead
* Deprecated `fillEmptyDates` prop in `src/Administration/Resources/app/administration/src/app/component/base/sw-chart/index.js`. Please use `fillEmptyValues` instead
