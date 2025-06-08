---
title: Fix category.cmsPage.locked error
issue: NEXT-17744
author: Niklas Limberg
author_email: n.limberg@shopware.com
author: NiklasLimberg
author_github: NiklasLimberg
---
# Administration
* Changed `saveSlotConfig()` in `sw-category-detail/index.js` to early return if there isn't a CMS Page selected
