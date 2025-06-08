---
title: Dashboard not showing correct order sum
issue: NEXT-19604
author: Daniela Puetz
author_github: @PuetzD
---
# Administration
* Changed `src/module/sw-dashboard/component/sw-dashboard-statistics/index.js` and `src/module/sw-dashboard/page/sw-dashboard-index/index.js` to use the `technicalName` instead of `name` since name needs the `state_machine_translation` table, also the translated value might not actually be equal to "paid", thus we should use the technicalName field directly
