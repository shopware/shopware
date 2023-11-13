---
title: Remove old cart-item templates in favor of new line-item template
issue: NEXT-23946
---
# Storefront
* Removed deprecated twig template files for line-items. Use `Resources/views/storefront/component/line-item/line-item.html.twig` instead:
    * Removed deprecated template `Resources/views/storefront/page/checkout/checkout-item.html.twig` 
    * Removed deprecated template `Resources/views/storefront/page/checkout/checkout-item-children.html.twig`
    * Removed deprecated template `Resources/views/storefront/page/checkout/confirm/confirm-item.html.twig`
    * Removed deprecated template `Resources/views/storefront/page/checkout/finish/finish-item.html.twig`
    * Removed deprecated template `Resources/views/storefront/component/checkout/offcanvas-item.html.twig`
    * Removed deprecated template `Resources/views/storefront/component/checkout/offcanvas-item-children.html.twig`
    * Removed deprecated template `Resources/views/storefront/page/account/order/line-item.html.twig`
    * Removed deprecated template `Resources/views/storefront/page/account/order-history/order-detail-list-item.html.twig`
    * Removed deprecated template `Resources/views/storefront/page/account/order-history/order-detail-list-item-children.html.twig`
    * Removed deprecated template `Resources/views/storefront/page/checkout/checkout-aside-item.html.twig`
    * Removed deprecated template `Resources/views/storefront/page/checkout/checkout-aside-item-children.html.twig`
* Removed deprecated twig templates for cart table headers. Use `Resources/views/storefront/component/checkout/cart-header.html.twig` instead:
    * Removed deprecated template `Resources/views/storefront/page/checkout/cart/cart-product-header.html.twig`
    * Removed deprecated template `Resources/views/storefront/page/checkout/confirm/confirm-product-header.html.twig`
    * Removed deprecated template `Resources/views/storefront/page/account/order/line-item-header.html.twig`
* Removed deprecated SCSS files
    * Removed deprecated SCSS file `Resources/app/storefront/src/scss/page/checkout/_cart-item.scss`
    * Removed deprecated SCSS file `Resources/app/storefront/src/scss/page/checkout/_cart-item-children.scss`
    * Removed deprecated SCSS file `Resources/app/storefront/src/scss/skin/page/checkout/_cart-item.scss`
