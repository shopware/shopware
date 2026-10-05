---
title: Keep order shipping country on recalculation
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Order\OrderConverter::assembleSalesChannelContext()` to use the order's own billing and shipping address when no address of the customer matches it, for example because the customer edited it, as in 6.7. Orders without a delivery still fall back to the customer's default shipping address. Recalculations, flows and payment handling of that order therefore use the tax state, tax rules, cash rounding and address rules of the address the order was placed with.
* In that case, `getActiveBillingAddress()` and `getActiveShippingAddress()` of the order context's customer return the order's address as a `CustomerAddressEntity`. Its ID is the ID of the `order_address`, not of a `customer_address`. Payment handlers and checkout gateway apps that load or update a customer address by this ID have to handle IDs that are not customer address IDs.
* Added the internal option keys `Shopware\Core\System\SalesChannel\Context\SalesChannelContextService::BILLING_ADDRESS` and `SHIPPING_ADDRESS`. The options passed to `AbstractSalesChannelContextFactory::create()` can now contain `CustomerAddressEntity` objects under these keys. Decorators that read these options should not assume that every value is a string or an array.
