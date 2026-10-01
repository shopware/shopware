---
title: A11y promotion discounts are not read as negative amounts
issue: NEXT-38710
---
# Storefront
* Changed the value of the currency to always positive and added a conditional check to display a minus sign (`&minus;`) if the value is negative, following in block and file:
    * `page_checkout_summary_shipping` in `views/storefront/page/checkout/summary/summary-shipping.html.twig`
    * `component_offcanvas_summary_content_info` in `views/storefront/component/checkout/offcanvas-cart-summary.html.twig`
    * `component_line_item_tax_price_inner` in `views/storefront/component/line-item/element/tax-price.html.twig`
    * `component_line_item_total_price_value` in `views/storefront/component/line-item/element/total-price.html.twig`
