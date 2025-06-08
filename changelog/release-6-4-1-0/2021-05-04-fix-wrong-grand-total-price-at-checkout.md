---
title: Fix wrong grand total price at checkout
issue: NEXT-14842
author_github: @Dominik28111
---
# Storefront
* Changed `totalPrice` to `rawTotal` in block `page_checkout_summary_total_value` in `src/Storefront/Resources/views/storefront/page/checkout/summary/summary-total.html.twig`.
