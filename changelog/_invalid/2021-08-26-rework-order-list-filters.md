---
title: Rework order list filters
issue: NEXT-16661
flag: NEXT-7530
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com
author_github: @lernhart
---
# Administration
* Added `timeframe` field to `src/Administration/Resources/app/administration/src/app/component/filter/sw-date-filter/index.js`
  * The timeframe has some default options to filter lists by one year, last quarter, one month and so on
* Added twig block `sw_date_filter_timeframe` in `src/Administration/Resources/app/administration/src/app/component/filter/sw-date-filter/sw-date-filter.html.twig`
* Deprecated computed `columns` in `src/Administration/Resources/app/administration/src/app/component/filter/sw-range-filter/index.js`
* Deprecated computed `gaps` in `src/Administration/Resources/app/administration/src/app/component/filter/sw-range-filter/index.js`
