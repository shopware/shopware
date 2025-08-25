---
title: Remove the asterisk next to every price
issue: NEXT-39225
---
# Storefront
* Added new block `component_product_box_price_tax_info` in `Resources/views/storefront/component/product/card/price-unit.html.twig` to display the tax and shipping information like on the product detail page.
* Deprecated the asterisk (*) next to every price in the following templates. The tax and shipping costs information is either already shown in the current context or will be replaced with an actual text.
    * Cart and order line items (Info already shown in the cart summary)
        - `Resources/views/storefront/component/line-item/element/total-price.html.twig` 
        - `Resources/views/storefront/component/line-item/element/unit-price.html.twig`
    * Cart summary (Info is already shown in the cart summary itself)
        - `Resources/views/storefront/page/checkout/summary/summary-position.html.twig`
        - `Resources/views/storefront/page/checkout/summary/summary-shipping.html.twig`
        - `Resources/views/storefront/page/checkout/summary/summary-total.html.twig`
        - `Resources/views/storefront/page/checkout/summary/summary-total-rounded.html.twig`
        - `Resources/views/storefront/component/checkout/offcanvas-cart-summary.html.twig`
    * Header cart widget (Info is not needed because no product can be added to the cart here)
        - `Resources/views/storefront/layout/header/actions/cart-widget.html.twig` 
    * Header search suggest box (Info is not needed because no product can be added to the cart here)
        - `Resources/views/storefront/layout/header/search-suggest.html.twig`
    * Product-box (Info is displayed as text instead when setting `core.listing.allowBuyInListing` is enabled)
        - `Resources/views/storefront/component/product/card/price-unit.html.twig` 
    * Buy-widget (Info is already shown on the product detail page)
        - `Resources/views/storefront/component/product/block-price.html.twig` 
        - `Resources/views/storefront/component/buy-widget/buy-widget-price.html.twig`