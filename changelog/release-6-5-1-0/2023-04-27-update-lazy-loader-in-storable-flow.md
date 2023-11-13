---
title: Update lazy loader in Storable Flow
issue: NEXT-26184
---
# Core
* Added `lazyLoad` functions to replace deprecated `lazy` functions in:
  * `Shopware\Core\Content\Flow\Dispatching\StorerCustomerGroupStorer`
  * `Shopware\Core\Content\Flow\Dispatching\CustomerRecoveryStorer`
  * `Shopware\Core\Content\Flow\Dispatching\CustomerStorer`
  * `Shopware\Core\Content\Flow\Dispatching\NewsletterRecipientStorer`
  * `Shopware\Core\Content\Flow\Dispatching\OrderStorer`
  * `Shopware\Core\Content\Flow\Dispatching\OrderTransactionStorer`
  * `Shopware\Core\Content\Flow\Dispatching\ProductStorer`
  * `Shopware\Core\Content\Flow\Dispatching\UserStorer`
* Changed `lazy` method in `Shopware\Core\Content\Flow\Dispatching\StorableFlow` to correct the lazy loader.
