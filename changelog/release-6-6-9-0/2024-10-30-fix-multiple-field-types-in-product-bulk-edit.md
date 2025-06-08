---
title: Fix multiple field types in product bulk edit
issue: NEXT-39417
author: Tim Theisinger
author_email: tim.theisinger@pickware.de
---

# Administration
* Changed the type of the fields `width`, `height`, `length`, `weight`, `purchaseUnit` and `referenceUnit` from `int` to `float` in `src/Administration/Resources/app/administration/src/module/sw-bulk-edit/page/sw-bulk-edit-product/index.js`  
