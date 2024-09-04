---
title: Fix shipping address in order detail work not correct after changes
issue: NEXT-37348
---
# Administration
* Changed `mutations` `setOrderAddressIds` in `src/module/sw-order/state/order-detail.store.js` to check the case with variables `orderAddressId` and `customerAddressId` have the same value.
* Changed `method` `onEditAddress` in `sw-order-address-selection` `component` to fix `orderAddressId` and `customerAddressId` always being the same when editing the address.
* Changed `watchers` `countryId` in `sw-customer-address-form` `component` to fix the country field not being displayed correctly.
