---
title: Abstract and reduce rule condition components
issue: NEXT-37386
author: Justus Maier
author_email: jmaier@notebooksbilliger.de
author_github: @justusNBB
---
# Core
* Added `Shopware\Core\Checkout\Cart\Event\CartEvent interface` shared by all CartEvents supporting the getCart method
* Added Cart- & SalesChannelEvent interfaces to CartEvents:
  * `Core/Checkout/Cart/Event/AfterLineItemAddedEvent`
  * `Core/Checkout/Cart/Event/AfterLineItemQuantityChangedEvent`
  * `Core/Checkout/Cart/Event/AfterLineItemRemovedEvent`
  * `Core/Checkout/Cart/Event/BeforeLineItemAddedEvent`
  * `Core/Checkout/Cart/Event/BeforeLineItemQuantityChangedEvent`
  * `Core/Checkout/Cart/Event/BeforeLineItemRemovedEvent`
  * `Core/Checkout/Cart/Event/CartBeforeSerializationEvent`
  * `Core/Checkout/Cart/Event/CartChangedEvent`
  * `Core/Checkout/Cart/Event/CartContextHashEvent`
  * `Core/Checkout/Cart/Event/CartCreatedEvent`
  * `Core/Checkout/Cart/Event/CartEvent`
  * `Core/Checkout/Cart/Event/CartLoadedEvent`
  * `Core/Checkout/Cart/Event/CartMergedEvent`
  * `Core/Checkout/Cart/Event/CartSavedEvent`
  * `Core/Checkout/Cart/Event/CartVerifyPersistEvent`
  * `Core/Checkout/Cart/Event/LineItemRemovedEvent`
* Deprecated `Shopware\Core\Checkout\Cart\Event\CartChangedEvent` method `getContext()` should return `Context`, the attribute name `$context` and the (missing) type of attribute `$cart`: Until this is solved CartChangedEvent cannot implement `ShopwareSalesChannelEvent`
* Added `Shopware\Core\Checkout\Cart\Event\CartChangedEvent` method `getSalesChannelContext()` for consistency
* Added `Shopware\Core\Checkout\Cart\Event\CartLoadedEvent` method `getContext()` for consistency
