<?php declare(strict_types=1);

return [
    Shopware\Core\Checkout\Cart\Rule\LineItemRule::class => <<<'EOD'
Matches multiple identifiers to a line item's keys. True if one identifier matches.
EOD
    ,
    Shopware\Core\Checkout\Cart\Rule\LineItemTotalPriceRule::class => <<<'EOD'
Matches a number to the current cart's line item total price.
EOD
    ,
    Shopware\Core\Checkout\Cart\Rule\GoodsCountRule::class => <<<'EOD'
Matches a number to the current cart's line item goods count.
EOD
    ,
    Shopware\Core\Checkout\Cart\Rule\LineItemsInCartCountRule::class => <<<'EOD'
Matches a number to the current cart's line item count.
EOD
    ,
    Shopware\Core\Checkout\Cart\Rule\CartHasDeliveryFreeItemRule::class => <<<'EOD'
Matches if the cart has a free delivery item.
EOD
    ,
    Shopware\Core\Checkout\Cart\Rule\CartWeightRule::class => <<<'EOD'
Matches a specific number to the current cart's total weight.
EOD
    ,
    Shopware\Core\Checkout\Cart\Rule\LineItemWithQuantityRule::class => <<<'EOD'
Matches a specific line item's quantity to the current line item's quantity.
EOD
    ,
    Shopware\Core\Checkout\Cart\Rule\LineItemUnitPriceRule::class => <<<'EOD'
Matches a specific number to a line item's price.
EOD
    ,
    Shopware\Core\Checkout\Cart\Rule\GoodsPriceRule::class => <<<'EOD'
Matches a specific number to the carts goods price.
EOD
    ,
    Shopware\Core\Checkout\Cart\Rule\LineItemTagRule::class => <<<'EOD'
Matches multiple tags to a line item's tag. True if one tag matches.
EOD
    ,
    Shopware\Core\Checkout\Cart\Rule\LineItemsInCartRule::class => <<<'EOD'
Matches multiple identifiers to a carts line item's identifier. True if one identifier matches.
EOD
    ,
    Shopware\Core\Checkout\Cart\Rule\CartAmountRule::class => <<<'EOD'
Matches a specific number to the carts total price.
EOD
    ,
    Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule::class => <<<'EOD'
Matches a specific type name to the line item's type.
EOD
    ,
    Shopware\Core\Checkout\Cart\Rule\LineItemWrapperRule::class => <<<'EOD'
Internally handled scope changes.
EOD
    ,
    Shopware\Core\Checkout\Customer\Rule\OrderCountRule::class => <<<'EOD'
Matches a specific number to the number of orders of the current customer.
EOD
    ,
    Shopware\Core\Checkout\Customer\Rule\ShippingZipCodeRule::class => <<<'EOD'
Matches multiple zip codes to the customer's active shipping address zip code. True if one zip code matches.
EOD
    ,
    Shopware\Core\Checkout\Customer\Rule\ShippingStreetRule::class => <<<'EOD'
Matches multiple street names to the customer's active shipping address street name. True if one street name matches.
EOD
    ,
    Shopware\Core\Checkout\Customer\Rule\DaysSinceLastOrderRule::class => <<<'EOD'
Matches a specific number of days to the last order creation date.
EOD
    ,
    Shopware\Core\Checkout\Customer\Rule\CustomerGroupRule::class => <<<'EOD'
Matches multiple customer groups to the current customers group. True if one customer group matches.
EOD
    ,
    Shopware\Core\Checkout\Customer\Rule\ShippingCountryRule::class => <<<'EOD'
Matches multiple countries to the customer's active shipping address country. True if one country matches.
EOD
    ,
    Shopware\Core\Checkout\Customer\Rule\IsNewCustomerRule::class => <<<'EOD'
Matches if a customer is new, by matching the `firstLogin` property with today.
EOD
    ,
    Shopware\Core\Checkout\Customer\Rule\CustomerNumberRule::class => <<<'EOD'
Matches multiple numbers to the active customers number.
EOD
    ,
    Shopware\Core\Checkout\Customer\Rule\BillingStreetRule::class => <<<'EOD'
Matches multiple street names to the customer's active billing address street name.
EOD
    ,
    Shopware\Core\Checkout\Customer\Rule\BillingZipCodeRule::class => <<<'EOD'
Matches multiple zip codes to the customer's active billing address zip code.
EOD
    ,
    Shopware\Core\Checkout\Customer\Rule\LastNameRule::class => <<<'EOD'
Exactly matches a string to the customer's last name.
EOD
    ,
    Shopware\Core\Checkout\Customer\Rule\BillingCountryRule::class => <<<'EOD'
Matches multiple countries to the customer's active billing address country.
EOD
    ,
    Shopware\Core\Framework\Rule\Container\OrRule::class => <<<'EOD'
Composition of rules. Matches if at least one rule matches.
EOD
    ,
    Shopware\Core\Framework\Rule\Container\NotRule::class => <<<'EOD'
Negates one rule.
EOD
    ,
    Shopware\Core\Framework\Rule\Container\XorRule::class => <<<'EOD'
Composition of rules. Matches if exactly one matches.
EOD
    ,
    Shopware\Core\Framework\Rule\Container\AndRule::class => <<<'EOD'
Composition of rules. Matches if all match.
EOD
    ,
    Shopware\Core\Framework\Rule\TimeRangeRule::class => <<<'EOD'
Matches a fixed time range to now.
EOD
    ,
    Shopware\Core\Framework\Rule\WeekdayRule::class => <<<'EOD'
Matches a fixed day of the week to now.
EOD
    ,
    Shopware\Core\Framework\Rule\DateRangeRule::class => <<<'EOD'
Match a fixed date range to now.
EOD
    ,
    Shopware\Core\Framework\Rule\SalesChannelRule::class => <<<'EOD'
Match a specific sales channel to the current context.
EOD
    ,
    Shopware\Core\System\Currency\Rule\CurrencyRule::class => <<<'EOD'
Match a specific currency to the current context.
EOD
    ,
    Shopware\Core\Checkout\Customer\Rule\DifferentAddressesRule::class => <<<'EOD'
Matches if active billing address is not the default.
EOD
    ,
];
