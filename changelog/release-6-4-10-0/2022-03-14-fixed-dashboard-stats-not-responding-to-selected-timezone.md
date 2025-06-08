---
title: Fixed dashboard stats not responding to selected timezone
issue: NEXT-19771
author: Eric Heinzl
author_email: e.heinzl@shopware.com
author_github: xPand4B
---
# Administration
* Changed `sw-dashboard-statistics` component to respond correctly to user timezone changes
  * Changed deprecated `sw-dashboard-index` page as well with the same behaviour
* Deprecated `todayBucket` in `administration/src/module/sw-dashboard/component/sw-dashboard-statistics/index.js`. Use `todayBucketCount` instead. 
* Deprecated `formatDate` in `administration/src/module/sw-dashboard/component/sw-dashboard-statistics/index.js`. Use `formatDateToISO` instead. 
* Added `dateWithUserTimezone` helper in `src/Administration/Resources/app/administration/src/core/service/utils/format.utils.ts`
