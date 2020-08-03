[titleEn]: <>(Rule classes)
[hash]: <>(article:rule)

List of all rule classes across Shopware 6.

### Checkout

[Shopware\Core\Checkout\Cart\Rule\LineItemRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Cart\Rule\LineItemRule)
 : Matches multiple identifiers to a line item's keys. True if one identifier matches.

[Shopware\Core\Checkout\Cart\Rule\LineItemTotalPriceRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Cart\Rule\LineItemTotalPriceRule)
 : Matches a number to the current cart's line item total price.

[Shopware\Core\Checkout\Cart\Rule\GoodsCountRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Cart\Rule\GoodsCountRule)
 : Matches a number to the current cart's line item goods count.

[Shopware\Core\Checkout\Cart\Rule\LineItemsInCartCountRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Cart\Rule\LineItemsInCartCountRule)
 : Matches a number to the current cart's line item count.

[Shopware\Core\Checkout\Cart\Rule\CartHasDeliveryFreeItemRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Cart\Rule\CartHasDeliveryFreeItemRule)
 : Matches if the cart has a free delivery item.

[Shopware\Core\Checkout\Cart\Rule\CartWeightRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Cart\Rule\CartWeightRule)
 : Matches a specific number to the current cart's total weight.

[Shopware\Core\Checkout\Cart\Rule\LineItemWithQuantityRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Cart\Rule\LineItemWithQuantityRule)
 : Matches a specific line item's quantity to the current line item's quantity. 

[Shopware\Core\Checkout\Cart\Rule\LineItemUnitPriceRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Cart\Rule\LineItemUnitPriceRule)
 : Matches a specific number to a line item's price.

[Shopware\Core\Checkout\Cart\Rule\GoodsPriceRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Cart\Rule\GoodsPriceRule)
 : Matches a specific number to the carts goods price. 

[Shopware\Core\Checkout\Cart\Rule\LineItemTagRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Cart\Rule\LineItemTagRule)
 : Matches multiple tags to a line item's tag. True if one tag matches.

[Shopware\Core\Checkout\Cart\Rule\LineItemsInCartRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Cart\Rule\LineItemsInCartRule)
 : Matches multiple identifiers to a carts line item's identifier. True if one identifier matches.

[Shopware\Core\Checkout\Cart\Rule\CartAmountRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Cart\Rule\CartAmountRule)
 : Matches a specific number to the carts total price.

[Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule)
 : Matches a specific type name to the line item's type.

[Shopware\Core\Checkout\Cart\Rule\LineItemWrapperRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Cart\Rule\LineItemWrapperRule)
 : Internally handled scope changes.

[Shopware\Core\Checkout\Customer\Rule\OrderCountRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Customer\Rule\OrderCountRule)
 : Matches a specific number to the number of orders of the current customer.

[Shopware\Core\Checkout\Customer\Rule\ShippingZipCodeRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Customer\Rule\ShippingZipCodeRule)
 : Matches multiple zip codes to the customer's active shipping address zip code. True if one zip code matches.

[Shopware\Core\Checkout\Customer\Rule\ShippingStreetRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Customer\Rule\ShippingStreetRule)
 : Matches multiple street names to the customer's active shipping address street name. True if one street name matches.

[Shopware\Core\Checkout\Customer\Rule\DaysSinceLastOrderRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Customer\Rule\DaysSinceLastOrderRule)
 : Matches a specific number of days to the last order creation date.

[Shopware\Core\Checkout\Customer\Rule\CustomerGroupRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Customer\Rule\CustomerGroupRule)
 : Matches multiple customer groups to the current customers group. True if one customer group matches.

[Shopware\Core\Checkout\Customer\Rule\CustomerTagRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Customer\Rule\CustomerTagRule)
 : Matches multiple tags to the current customer's tags. True if one tag matches.

[Shopware\Core\Checkout\Customer\Rule\ShippingCountryRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Customer\Rule\ShippingCountryRule)
 : Matches multiple countries to the customer's active shipping address country. True if one country matches.

[Shopware\Core\Checkout\Customer\Rule\IsNewCustomerRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Customer\Rule\IsNewCustomerRule)
 : Matches if a customer is new, by matching the `firstLogin` property with today.

[Shopware\Core\Checkout\Customer\Rule\DifferentAddressesRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Customer\Rule\DifferentAddressesRule)
 : Matches if active billing address is not the default.

[Shopware\Core\Checkout\Customer\Rule\CustomerNumberRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Customer\Rule\CustomerNumberRule)
 : Matches multiple numbers to the active customers number.

[Shopware\Core\Checkout\Customer\Rule\BillingStreetRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Customer\Rule\BillingStreetRule)
 : Matches multiple street names to the customer's active billing address street name.

[Shopware\Core\Checkout\Customer\Rule\BillingZipCodeRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Customer\Rule\BillingZipCodeRule)
 : Matches multiple zip codes to the customer's active billing address zip code.

[Shopware\Core\Checkout\Customer\Rule\LastNameRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Customer\Rule\LastNameRule)
 : Exactly matches a string to the customer's last name.

[Shopware\Core\Checkout\Customer\Rule\BillingCountryRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Checkout\Customer\Rule\BillingCountryRule)
 : Matches multiple countries to the customer's active billing address country.

### Framework

[Shopware\Core\Framework\Rule\Container\OrRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Framework\Rule\Container\OrRule)
 : Composition of rules. Matches if at least one rule matches.

[Shopware\Core\Framework\Rule\Container\NotRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Framework\Rule\Container\NotRule)
 : Negates one rule.

[Shopware\Core\Framework\Rule\Container\XorRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Framework\Rule\Container\XorRule)
 : Composition of rules. Matches if exactly one matches.

[Shopware\Core\Framework\Rule\Container\AndRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Framework\Rule\Container\AndRule)
 : Composition of rules. Matches if all match.

[Shopware\Core\Framework\Rule\TimeRangeRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Framework\Rule\TimeRangeRule)
 : Matches a fixed time range to now.

[Shopware\Core\Framework\Rule\WeekdayRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Framework\Rule\WeekdayRule)
 : Matches a fixed day of the week to now. 

[Shopware\Core\Framework\Rule\DateRangeRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Framework\Rule\DateRangeRule)
 : Match a fixed date range to now.

[Shopware\Core\Framework\Rule\SalesChannelRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\Framework\Rule\SalesChannelRule)
 : Match a specific sales channel to the current context.

### System

[Shopware\Core\System\Currency\Rule\CurrencyRule](https://github.com/shopware/platform/tree/master/src/Core/Shopware\Core\System\Currency\Rule\CurrencyRule)
 : Match a specific currency to the current context.


