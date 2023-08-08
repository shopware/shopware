---
title: Catch order not found errors in Storefront
issue: NEXT-28434
---
# Storefront
* Removed exception throw of `\Shopware\Core\Checkout\Order\OrderException::orderNotFound` in `\Shopware\Storefront\Controller\AccountOrderController::editOrder` and replaced it with a flash message
