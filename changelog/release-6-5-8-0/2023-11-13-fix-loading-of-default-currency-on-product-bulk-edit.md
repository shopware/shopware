---
title: Fix loading of default currency on product bulk edit
issue: NEXT-31746
author: Leon Rustmeier
author_email: l.rustmeier@heptacom.de
---

# Administration
* Deprecated method `currencyCriteria` in `src/Administration/Resources/app/administration/src/module/sw-bulk-edit/page/sw-bulk-edit-product/index.js`, will be removed
* Changed `loadDefaultCurrency` method in `sw-bulk-edit-product` component to fix the loading of default currency
