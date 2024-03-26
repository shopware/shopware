---
title: Remove Storefront Twig, JS and CSS deprecations for v6.6.0
issue: NEXT-32085
---
# Storefront
* Removed all deprecated data-attribute selectors in Twig and JavaScript
    * Removed deprecated selector `data-search-form`, use `data-search-widget` instead.
    * Removed deprecated selector `data-offcanvas-cart`, use `data-off-canvas-cart` instead.
    * Removed deprecated selector `data-collapse-footer`, use `data-collapse-footer-columns` instead.
    * Removed deprecated selector `data-offcanvas-menu`, use `data-off-canvas-menu` instead.
    * Removed deprecated selector `data-offcanvas-account-menu`, use `data-account-menu` instead.
    * Removed deprecated selector `data-offcanvas-tabs`, use `data-off-canvas-tabs` instead.
    * Removed deprecated selector `data-offcanvas-filter`, use `data-off-canvas-filter` instead.
    * Removed deprecated selector `[data-bs-toggle="modal"][data-url]`, use `[data-ajax-modal][data-url]` instead.
* Removed deprecated JS-plugins
    * Removed deprecated JS-plugin `Ellipsis`
    * Removed deprecated JS-plugin `Fading`
* Removed deprecated helper `CmsSlotOptionValidatorHelper`
* Removed deprecated service `CmsSlotReloadService`
* Removed deprecated parameter `closeable` from `OffCanvas.setContent` (`Resources/app/storefront/src/plugin/offcanvas/offcanvas.plugin.js`). Accepted parameters are now `setContent(content, delay)`.
* Removed deprecated CSS
    * Removed deprecated SCSS file `Resources/app/storefront/src/scss/page/checkout/_aside-children.scss`
    * Removed deprecated CSS for `.line-item-ordernumber`
    * Removed deprecated CSS for `.cart-item` and all corresponding sub-selectors `cart-item-*`
    * Removed deprecated CSS for `.checkout-aside-item`
* Removed deprecated Twig blocks
    * Removed deprecated block `component_line_item_type_generic_order_number` in `Resources/views/storefront/component/line-item/type/generic.html.twig`, use `component_line_item_type_generic_product_number` instead.
    * Removed deprecated block `component_line_item_type_product_order_number` in `Resources/views/storefront/component/line-item/type/product.html.twig`, use `component_line_item_type_product_number` instead.
* Removed deprecated Twig variables
    * Removed deprecated boolean variable `productLink` from `Resources/views/storefront/component/line-item/element/image.html.twig`
    * Removed deprecated boolean variable `productLink` from `Resources/views/storefront/component/line-item/element/label.html.twig`
