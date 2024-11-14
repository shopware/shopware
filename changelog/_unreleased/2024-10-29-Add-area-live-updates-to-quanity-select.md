---
title: Improved accessibility of quantity select with live updates 
issue: NEXT-38927
---
# Storefront
* Changed `quantity-selector.plugin.js` to add area live updates for quantity changes:
  * Added new private method `_updateAriaLive` which will update the corresponding area live element.
  * Added new option `ariaLiveUpdates` to configure if live updates should be made. Default is set to `true`.
  * Added new option `ariaLiveUpdateMode` to configure the live update for different use cases. Use value `live` for immediate updates on value change. Use value `onload` for updates after page reload, for example for auto submit forms. Default is `live`.
  * Added new option `ariaLiveTextValueToken` which is the placeholder tag within the text snippet for the quantity value. Default is `%quantity%`.
  * Added new option `ariaLiveTextProductToken` which is the placeholder tag within the text snippet for the product name. Default is `%product%`.
* Added new block `buy_widget_buy_quantity_live_area` to `buy-widget-form.html.twig` which contains the area live element.
* Added new block `page_product_detail_buy_quantity_live_area` to `buy-widget-form.html.twig` which contains the area live element.
* Added new block `component_line_item_quantity_select_live_area` to `quantity.html.twig` which contains the area live element.
* Added new snippets `areaLiveText`, `labelWithProduct`, `increaseOfProduct`, and `decreaseOfProduct` to the snippet scope `component.product.quantitySelect`.
* Changed the snippets in `quantity.html.twig` to the snippets containing the product name, so that users know which product quantity they are changing within lists of line items, like the cart.
