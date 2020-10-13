---
title: Restoring Cart after customer logged in
issue: NEXT-10527
flag: FEATURE_NEXT_10058
---
# Core
*  Added `customer_id` nullable unique column in `sales_channel_api_context` table.
*  Added new `Shopware\Core\Checkout\Cart\Event\CartMergedEvent` is fired after the guest's cart is merged with the customer's cart.
*  Added new `Shopware\Core\System\SalesChannel\Event\SalesChannelContextRestoredEvent` is fired after a sales channel context is restored.
*  Added new `Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer` class that handles restoring a customer's sales channel context and cart after logging in.
*  Added third parameter `customer_id` in `Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister::save()` method that allow save a customer's context.
*  Added second parameter `customer_id` in `Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister::load()` method that allow load a customer's context using a customer_id.
*  Changed `Core/Checkout/Customer/SalesChannel/LogoutRoute::logout()` method that returns `ContextTokenResponse` instead of `NoContentResponse`.
*  Changed `Core/Checkout/Customer/SalesChannel/LogoutRoute::logout()` method that allows a `reqplace-token` request parameter that allow to replace context's token after logging out.
*  Changed `Core/Checkout/Customer/SalesChannel/ChangePasswordRoute::change()` method that return `ContextTokenResponse` instead of `SuccessResponse`.
___
# Storefront
*  Added Cart merged hints in session flash bag after the customer's shopping cart is merged with the last visited
___
# Upgrade Information
## Customer's sales channel context is restored after logged in
- Each customer now has a unique sales channel context, which means it will be shared across devices and browsers, including its cart.
- Which this change, when working with `SalesChannelContextPersister`, you should pass a third parameter `customer_id` in `SalesChannelContextPersister::save()` to save customer's customer's context.

