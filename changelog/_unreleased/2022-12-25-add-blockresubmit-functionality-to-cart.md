---
title: Add blockResubmit functionality to cart validators
issue: NEXT-25555
author: Altay Akkus
author_email: altayakkus1993@googlemail.com
author_github: @AltayAkkus
---
# Core
*  Added feature `blockResubmit` for cart validators, which should block the order, but not prevent the user from retrying.
*  Added Twig checks for `blockResubmit` in `src/Storefront/Resources/views/storefront/page/checkout/confirm/index.html.twig`.
*  Added `blockResubmit` method to `src/Core/Checkout/Cart/Error/Error.php`.
*  Added `blockResubmit` error collection in `src/Core/Checkout/Cart/Error/ErrorCollection.php`.
*  Added `blockResubmit` to the errors facade in `src/Core/Checkout/Cart/Facade/ErrorsFacade.php` via the method `resubmittable()`.
*  Added `blockResubmit` and `blockOrder` parameters to the TestError class in `tests/unit/php/Core/Checkout/Cart/Order/TestError.php`.
*  Added `blockResubmit` check to `testErrorTypes` and created `testErrorResubmittable` in `tests/unit/php/Core/Checkout/Cart/Order/ErrorCollectionTest.php`.
___
# Cart errors without blocking resubmitting - blockResubmit
`blockResubmit` allows developers to create cart errors that do not block the user from trying to submit their cart again.
This can be useful if the error can be automatically corrected (like `PaymentMethodChangedError`), or does not require user intervention but user approval (like `ProductOutOfStockError`).
You can add it to your error by overriding the default `blockResubmit(): bool` method from `Shopware\Core\Checkout\Cart\Error\Error`, `true` blocks the resubmit button, `false` does not.
If a cart has multiple errors, a single `blockResubmit = true` overrides all other errors, just like `blockOrder`.  