---
title: Catch payment method exceptions on order finish
issue: NEXT-27403
---
# Storefront
* Added `invalidPaymentButOrderStored` method to `Shopware\Core\Checkout\Cart\CartException`.
* Changed `order` method in `Shopware\Storefront\Controller\CheckoutController` to catch additional `InvalidOrderException` and `CartException::CART_PAYMENT_INVALID_ORDER_STORED_CODE` exceptions.
