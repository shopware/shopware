---
title: Fix billing address disabled after activating
issue: NEXT-15168
---
# Administration
*  Changed block `sw_customer_address_form_options_default_shipping_address` and block `sw_customer_address_form_options_default_billing_address` in `src/module/sw-customer/component/sw-customer-address-form-options/sw-customer-address-form-options.html.twig` to remove `disabled` property.
* Changed method `onChangeDefaultAddress` in `app/administration/src/module/sw-customer/view/sw-customer-detail-addresses/index.js` to restore the value of default shipping address or billing address on unchecked options.
