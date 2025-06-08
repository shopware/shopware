---
title: Promotions will be created with an error thrown in the admin
issue: NEXT-36511
---
# Administration
* Changed method `loadEntityData` in `src/Administration/Resources/app/administration/src/module/sw-promotion-v2/page/sw-promotion-v2-detail/index.js` to add a check to ensure `this.promotionId` is not `null`.
