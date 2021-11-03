---
title: Remove slot config overriding template config bug
issue: NEXT-17940
---
# Administration
* Changed `app/administration/src/module/sw-category/page/sw-category-detail/index.js` behavior, to not replace the template config with the slot config anymore
* Deprecated method `saveSlotConfig` in `app/administration/src/module/sw-category/page/sw-category-detail/index.js`
