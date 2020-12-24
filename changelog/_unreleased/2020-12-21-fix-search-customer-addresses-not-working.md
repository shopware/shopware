---
title: Fix search on customer addresses not working
issue: NEXT-12956
---
# Administration
* Changed `sw-one-to-many-grid` component in `src/module/sw-customer/view/sw-customer-detail-addresses/sw-customer-detail-addresses.html.twig` to update attribute `:localMode="false"`.
* Added computed `customerAddressRepository` in `src/module/sw-customer/view/sw-customer-detail-addresses/index.js` to register repositories.
* Changed method `onConfirmDeleteAddress` in `src/module/sw-customer/view/sw-customer-detail-addresses/index.js` to call API when delete address.
