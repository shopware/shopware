---
title: Fix quantity alignment of order line item
issue: NEXT-25371
---
# Storefront
* Changed block `component_line_item_quantity_display` to edit bootstrap class in `views/storefront/component/line-item/element/quantity.html.twig`
* Changed in `app/storefront/src/scss/component/_line-item.scss` to edit class `line-item-quantity` for quantity center alignment adjustment
* Changed in `/app/storefront/src/scss/page/checkout/_cart.scss` to add class `cart-header-quantity` to make header title `Quantity` align center 
