---
title: Cleanup cancel order modal
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Changed `@Storefront/storefront/page/account/order/index.html.twig` to only include the cancel modal if it has been enabled by `core.cart.enableOrderRefunds`
* Added block `page_checkout_aside_cancel_order_modal_content` to the template `@Storefront/storefront/page/account/order/index.html.twig`
* Changed the template `@Storefront/storefront/page/account/order/cancel-order-modal.html.twig` to follow the stylings of the updated modal and made the `id` of the modal header unique to follow HTML specifications
