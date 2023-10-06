---
title: Skip the calculation and rendering of the checkout info widget if the cart is empty
issue: NEXT-20691
---
# Storefront
* Changed `\Shopware\Storefront\Controller\CheckoutController::info()` to return an empty response with HTTP status code `204 - No Content` if cart is empty, beginning with `v6.5.0.0`.
* Changed `cart-widget.plugin.js` to handle status code `204` correctly.
___
# Next Major Version Changes
## Possible empty response in checkout info route

The route `/widgets/checkout/info` will now return an empty response with HTTP status code `204 - No Content`, as long as the cart is empty, instead of loading the page and responding with a rendered template.

If you call that route manually in your extensions, please ensure to handle the `204` status code correctly.

Additionally, as the whole info widget pagelet will not be loaded anymore for empty carts, your event subscriber or app scripts for that page also won't be executed anymore for empty carts.
