---
title: Add toISODate() filter
issue: NEXT-17055
___
# Administration
* Added new `toISODate()` filter in `src/core/service/utils/format.utils.js`
* Changed method `formatDate()` in `src/module/sw-dashboard/page/sw-dashboard-index/index.js` to use the newly added `toISODate()`filter
