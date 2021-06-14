---
title: Reworked nested line item handling for checkout
issue: NEXT-14313
---
# Storefront
*  Changed nested Line Item behaviour to have a collapsable children Line Item box instead of separate, displayed items 
*  Added new template files in `platform/src/Storefront/Resources/views/storefront/page/checkout/`:
    * `checkout-aside-item-children.html.twig`
    * `checkout-item-children.html.twig`
*  Added new style sheet files in `platform/src/Storefront/Resources/app/storefront/src/scss/page/checkout/`:
    * `_aside-children.scss`
    * `_cart-item-children.scss`
