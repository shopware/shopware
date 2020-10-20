---
title: Add address to customer selection in add order
issue: NEXT-8309
author: Alexander Batenburg
author_email: alexander@h1.nl 
author_github: @alexbaat
---
# Administration
*  Added customer default billing address info below customer name in customer selectbox in `src/module/sw-order/component/sw-order-create-details-header/sw-order-create-details-header.html.twig`
*  Added css for properly displaying address in `src/module/sw-order/page/sw-order-create/sw-order-create.scss`
*  Added `customerCriteria` in `src/module/sw-order/component/sw-order-create-details-header/index.js` for associate customer with billingAddress