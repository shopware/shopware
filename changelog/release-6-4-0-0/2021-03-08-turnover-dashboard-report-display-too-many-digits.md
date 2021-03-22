---
title: Turnover dashboard report display too many digits
issue: NEXT-13845
---
# Administration
* Added number "2" as a third parameter in the chartOptionsOrderSum's formatter callback in `src/Administration/Resources/app/administration/src/module/sw-dashboard/page/sw-dashboard-index/index.js` to only show 2 decimals number in chart yaxis.
