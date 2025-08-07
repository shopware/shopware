---
title: Cart persistence behaviour
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Added `Shopware\Core\Checkout\CheckoutPermissions` to collect permissions for the `SalesChannelContext` and `CartBehaviour` at a single place.
* Changed `Shopware\Core\Checkout\Cart\CartBehavior` to remove `$isRecalculation`. Use granular permissions instead.
* Changed `Shopware\Core\Checkout\Cart\AbstractCartPersister` to skip persistence on `CheckoutPermissions::SKIP_CART_PERSISTENCE` cart behaviour permission.
* Changed `Shopware\Core\Checkout\Cart\Order\OrderConverter::ADMIN_EDIT_ORDER_PERMISSIONS` to include `CheckoutPermissions::SKIP_CART_PERSISTENCE`, `CheckoutPermissions::AUTOMATIC_PROMOTION_DELETION_NOTICES` and `CheckoutPermissions::SKIP_PRIMARY_IDS` to replace `CartBehaviour::isRecalculation`
___
# Upgrade Information
## Deprecation of `CartBehavior::isRecalculation`
`CartBehavior::isRecalculation` is deprecated.
Please use granular permissions instead, a list of them can be found in `Shopware\Core\Checkout\CheckoutPermissions`.
Note that a new `CartBehaviour` should be created with the permissions of the `SalesChannelContext`.
## Skip cart persistence with `CheckoutPermissions::SKIP_CART_PERSISTENCE`
Flag the sales channel context or cart behaviour with `AbstractCartPersister::SKIP_PERSISTENCE_PERMISSION` to skip persisting the cart. Useful to trigger a memory only cart calculation:
```php
$calculatedCart = $updatedContext->withPermissions(
    [AbstractCartPersister::SKIP_PERSISTENCE_PERMISSION => true],
    fn (SalesChannelContext $context): Cart => $this->cartService->recalculate($originalCart, $context),
);
```
___
# Next Major Version Changes
## Removal of `CartBehavior::isRecalculation`
`CartBehavior::isRecalculation` was removed.
Please use granular permissions instead, a list of them can be found in `Shopware\Core\Checkout\CheckoutPermissions`.
Note that a new `CartBehaviour` should be created with the permissions of the `SalesChannelContext`.
