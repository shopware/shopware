---
title: Allow statistic date ranges to be extended
issue: NEXT-24793
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Administration
* Removed validator for `availableRanges` in component `src/app/component/base/sw-chart-card/index.js`
* Changed computed property `rangeValuesMap` of component `src/module/sw-dashboard/component/sw-dashboard-statistics/index.ts` to be extensible
