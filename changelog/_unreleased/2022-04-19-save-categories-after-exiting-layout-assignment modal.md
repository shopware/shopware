---
title: Save categories after exiting layout-assignment modal
issue: NEXT-20283
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Removed deprecation of the `confirm` event in `sw-cms-layout-assignment-modal/index.js` and `sw-cms-detail/index.js`
* Changed `sw-cms-detail/index.js:onConfirmLayoutAssignment()` to save the layout when confirming the `sw-cms-layout-assignment-modal`