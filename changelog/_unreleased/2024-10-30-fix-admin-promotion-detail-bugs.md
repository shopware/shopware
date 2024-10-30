---
title: Fix administration promotion detail bugs
issue: NEXT-00000
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Administration
* Changed `tooltipSave` in `sw-promotion-v2-detail/index.js` to don't check for the wrong permission
* Changed `createPromotion` in `sw-promotion-v2-detail/index.js` to `deprecated` because it's now unused
* Changed `savePromotion` in `sw-promotion-v2-detail/index.js` to correctly redirect from the create to the detail page only after a successful save
