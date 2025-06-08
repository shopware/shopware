---
title: Fix administration promotion detail bugs
issue: NEXT-39462
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Administration
* Changed `tooltipSave` computed in `sw-promotion-v2-detail` component to get rid of the wrong permission check.
* Changed `savePromotion` method in `sw-promotion-v2-detail` component to correctly redirect from the create to the detail page only after a successful save.
* Deprecated `createPromotion` method in `sw-promotion-v2-detail` component due to unused.
