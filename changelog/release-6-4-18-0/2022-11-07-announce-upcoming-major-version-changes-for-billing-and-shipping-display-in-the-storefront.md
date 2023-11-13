---
title: Announce upcoming major version changes for billing and shipping display in the storefront
issue: NEXT-23935
author: Tobias Berge
author_email: t.berge@shopware.com
author_github: @tobiasberge
---
# Storefront
* Deprecated address positions to display the shipping address before the billing address in `Resources/views/storefront/page/checkout/confirm/confirm-address.html.twig`
    * Deprecated position of block `page_checkout_confirm_address_billing`, will be displayed after `page_checkout_confirm_address_shipping` and vice-versa
    * Deprecated block `page_checkout_confirm_address_shipping_data_equal`, block will be moved into billing address cart and renamed to `page_checkout_confirm_address_billing_data_equal`
* Deprecated address positions to display the shipping address before the billing address in `Resources/views/storefront/page/checkout/finish/finish-address.html.twig`
    * Deprecated position of block `page_checkout_finish_address_billing`, will be displayed after `page_checkout_finish_address_shipping` and vice-versa 
