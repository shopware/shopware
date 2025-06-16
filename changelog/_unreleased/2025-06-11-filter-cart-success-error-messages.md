---
title: Filter cart success error messages
issue: #10146
---
# Storefront
* Changed error convert behaviour in `\Shopware\Storefront\Controller\CartLineItemController`. `\Shopware\Core\Checkout\Promotion\Cart\PromotionCartAddedInformationError` will be converted into a success message on every `CartLineItemController` route.
