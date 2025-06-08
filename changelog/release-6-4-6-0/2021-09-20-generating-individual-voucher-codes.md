---
title: Generating individual voucher codes
issue: NEXT-14005
---
# Administration
* Added event `items-delete-finish` into component `sw-one-to-many-grid` in `/module/sw-promotion-v2/component/promotion-codes/sw-promotion-v2-individual-codes-behavior/sw-promotion-v2-individual-codes-behavior.html.twig`.
* Added event `delete-individual-codes-finish` into `router-view` in `/module/sw-promotion-v2/page/sw-promotion-v2-detail/sw-promotion-v2-detail.html.twig`.
* Added event `delete-finish` into component `sw-promotion-v2-individual-codes-behavior` in `/module/sw-promotion-v2/view/sw-promotion-v2-detail-base/sw-promotion-v2-detail-base.html.twig`.
* Added method `onDeleteIndividualCodesFinish` in `/module/sw-promotion-v2/page/sw-promotion-v2-detail/index.js` to handle action save after successfully deleted.
