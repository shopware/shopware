---
title: Switch billing and shipping display order
issue: NEXT-23637
---
# Storefront
* Changed `Resources/views/storefront/page/checkout/confirm/confirm-address.html.twig` by switching address positions to display the shipping address before the billing address in
* Changed block `page_checkout_confirm_address_shipping_data_equal` by moving it to billing address cart and renamed it to `page_checkout_confirm_address_billing_data_equal`
* Changed `Resources/views/storefront/page/checkout/finish/finish-address.html.twig` by switching address positions to display the shipping address before the billing address 
