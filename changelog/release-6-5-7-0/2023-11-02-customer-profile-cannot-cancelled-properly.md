---
title: Customer profile cannot cancelled properly
issue: NEXT-29512
author: Florian Keller
author_email: f.keller@shopware.com

---
# Administration
* Changed `src/Administration/Resources/app/administration/src/module/sw-customer/page/sw-customer-detail/index.js` and create new function `loadCustomer` to reload the customer after abort editing
