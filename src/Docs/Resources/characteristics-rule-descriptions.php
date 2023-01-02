<?php declare(strict_types=1);

use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Checkout\Cart\Rule\CartHasDeliveryFreeItemRule;
use Shopware\Core\Checkout\Cart\Rule\CartVolumeRule;
use Shopware\Core\Checkout\Cart\Rule\CartWeightRule;
use Shopware\Core\Checkout\Cart\Rule\GoodsCountRule;
use Shopware\Core\Checkout\Cart\Rule\GoodsPriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemClearanceSaleRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemCreationDateRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemDimensionHeightRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemDimensionLengthRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemDimensionVolumeRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemDimensionWeightRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemDimensionWidthRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemGoodsTotalRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemGroupRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemInCategoryRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemInProductStreamRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemIsNewRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemListPriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfManufacturerRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemPromotedRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemPropertyRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemPurchasePriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemReleaseDateRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemsInCartCountRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemsInCartRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemStockRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemTagRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemTaxationRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemTotalPriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemUnitPriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemWithQuantityRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemWrapperRule;
use Shopware\Core\Checkout\Cart\Rule\PaymentMethodRule;
use Shopware\Core\Checkout\Cart\Rule\ShippingMethodRule;
use Shopware\Core\Checkout\Customer\Rule\BillingCountryRule;
use Shopware\Core\Checkout\Customer\Rule\BillingStreetRule;
use Shopware\Core\Checkout\Customer\Rule\BillingZipCodeRule;
use Shopware\Core\Checkout\Customer\Rule\CustomerCustomFieldRule;
use Shopware\Core\Checkout\Customer\Rule\CustomerGroupRule;
use Shopware\Core\Checkout\Customer\Rule\CustomerNumberRule;
use Shopware\Core\Checkout\Customer\Rule\CustomerTagRule;
use Shopware\Core\Checkout\Customer\Rule\DaysSinceLastOrderRule;
use Shopware\Core\Checkout\Customer\Rule\DifferentAddressesRule;
use Shopware\Core\Checkout\Customer\Rule\IsCompanyRule;
use Shopware\Core\Checkout\Customer\Rule\IsNewCustomerRule;
use Shopware\Core\Checkout\Customer\Rule\LastNameRule;
use Shopware\Core\Checkout\Customer\Rule\OrderCountRule;
use Shopware\Core\Checkout\Customer\Rule\OrderTotalAmountRule;
use Shopware\Core\Checkout\Customer\Rule\ShippingCountryRule;
use Shopware\Core\Checkout\Customer\Rule\ShippingStreetRule;
use Shopware\Core\Checkout\Customer\Rule\ShippingZipCodeRule;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\NotRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Container\XorRule;
use Shopware\Core\Framework\Rule\DateRangeRule;
use Shopware\Core\Framework\Rule\SalesChannelRule;
use Shopware\Core\Framework\Rule\TimeRangeRule;
use Shopware\Core\Framework\Rule\WeekdayRule;
use Shopware\Core\System\Currency\Rule\CurrencyRule;
use Shopware\Core\System\Language\Rule\LanguageRule;

return [
    AlwaysValidRule::class => <<<'EOD'
Matches always
EOD
    ,
    CartAmountRule::class => <<<'EOD'
Matches a specific number to the carts total price.
EOD
    ,
    CartHasDeliveryFreeItemRule::class => <<<'EOD'
Matches if the cart has a free delivery item.
EOD
    ,
    CartVolumeRule::class => <<<'EOD'
Matches a specific number to the current cart's total volume.
EOD
    ,
    CartWeightRule::class => <<<'EOD'
Matches a specific number to the current cart's total weight.
EOD
    ,
    GoodsCountRule::class => <<<'EOD'
Matches a number to the current cart's line item goods count.
EOD
    ,
    GoodsPriceRule::class => <<<'EOD'
Matches a specific number to the carts goods price.
EOD
    ,
    LineItemClearanceSaleRule::class => <<<'EOD'
Matches a specific line item which is on clearance sale
EOD
    ,
    LineItemCreationDateRule::class => <<<'EOD'
Matches if a line item has a specific creation date.
EOD
    ,
    LineItemCustomFieldRule::class => <<<'EOD'
Matches if a line item has a specific custom field.
EOD
    ,
    LineItemDimensionHeightRule::class => <<<'EOD'
Matches a specific line item's height.
EOD
    ,
    LineItemDimensionLengthRule::class => <<<'EOD'
Matches a specific line item's length.
EOD
    ,
    LineItemDimensionVolumeRule::class => <<<'EOD'
Matches a specific line item's volume.
EOD
    ,
    LineItemDimensionWeightRule::class => <<<'EOD'
Matches a specific line item's weight.
EOD
    ,
    LineItemDimensionWidthRule::class => <<<'EOD'
Matches a specific line item's width.
EOD
    ,
    LineItemGroupRule::class => <<<'EOD'
Matches if a line item has a specific group.
EOD
    ,
    LineItemInCategoryRule::class => <<<'EOD'
Matches if a line item is in a specific category.
EOD
    ,
    LineItemInProductStreamRule::class => <<<'EOD'
Matches if a line item is in a specific dynamic product group.
EOD
    ,
    LineItemIsNewRule::class => <<<'EOD'
Matches if a line item is marked as new.
EOD
    ,
    LineItemListPriceRule::class => <<<'EOD'
Matches a specific line item has a specific list price.
EOD
    ,
    LineItemOfManufacturerRule::class => <<<'EOD'
Matches a specific line item has a specific manufacturer.
EOD
    ,
    LineItemOfTypeRule::class => <<<'EOD'
Matches a specific type name to the line item's type.
EOD
    ,
    LineItemPromotedRule::class => <<<'EOD'
Matches if a line item is promoted.
EOD
    ,
    LineItemPropertyRule::class => <<<'EOD'
Matches if a line item has a specific property.
EOD
    ,
    LineItemPurchasePriceRule::class => <<<'EOD'
Matches if a line item has a specific purchase price.
EOD
    ,
    LineItemReleaseDateRule::class => <<<'EOD'
Matches a specific line item's release date.
EOD
    ,
    LineItemRule::class => <<<'EOD'
Matches multiple identifiers to a line item's keys. True if one identifier matches.
EOD
    ,
    LineItemStockRule::class => <<<'EOD'
Matches a specific line item's available stock.
EOD
    ,
    LineItemTagRule::class => <<<'EOD'
Matches multiple tags to a line item's tag. True if one tag matches.
EOD
    ,
    LineItemTaxationRule::class => <<<'EOD'
Matches if a line item has a specific tax.
EOD
    ,
    LineItemTotalPriceRule::class => <<<'EOD'
Matches a number to the current cart's line item total price.
EOD
    ,
    LineItemUnitPriceRule::class => <<<'EOD'
Matches a specific number to a line item's price.
EOD
    ,
    LineItemWithQuantityRule::class => <<<'EOD'
Matches a specific line item's quantity to the current line item's quantity.
EOD
    ,
    LineItemWrapperRule::class => <<<'EOD'
Internally handled scope changes.
EOD
    ,
    LineItemsInCartCountRule::class => <<<'EOD'
Matches a number to the current cart's line item count.
EOD
    ,
    LineItemsInCartRule::class => <<<'EOD'
Matches multiple identifiers to a carts line item's identifier. True if one identifier matches.
EOD
    ,
    PaymentMethodRule::class => <<<'EOD'
Matches if a specific payment method is used
EOD
    ,
    ShippingMethodRule::class => <<<'EOD'
Matches if a specific shipping method is used
EOD
    ,
    BillingCountryRule::class => <<<'EOD'
Matches multiple countries to the customer's active billing address country.
EOD
    ,
    BillingStreetRule::class => <<<'EOD'
Matches multiple street names to the customer's active billing address street name.
EOD
    ,
    BillingZipCodeRule::class => <<<'EOD'
Matches multiple zip codes to the customer's active billing address zip code.
EOD
    ,
    CustomerGroupRule::class => <<<'EOD'
Matches multiple customer groups to the current customers group. True if one customer group matches.
EOD
    ,
    CustomerNumberRule::class => <<<'EOD'
Matches multiple numbers to the active customers number.
EOD
    ,
    CustomerTagRule::class => <<<'EOD'
Matches a tag set to customers
EOD
    ,
    DaysSinceLastOrderRule::class => <<<'EOD'
Matches a specific number of days to the last order creation date.
EOD
    ,
    DifferentAddressesRule::class => <<<'EOD'
Matches if active billing address is not the default.
EOD
    ,
    IsCompanyRule::class => <<<'EOD'
Matches if the customer is a company
EOD
    ,
    IsNewCustomerRule::class => <<<'EOD'
Matches if a customer is new, by matching the `firstLogin` property with today.
EOD
    ,
    LastNameRule::class => <<<'EOD'
Exactly matches a string to the customer's last name.
EOD
    ,
    OrderCountRule::class => <<<'EOD'
Matches a specific number to the number of orders of the current customer.
EOD
    ,
    ShippingCountryRule::class => <<<'EOD'
Matches multiple countries to the customer's active shipping address country. True if one country matches.
EOD
    ,
    ShippingStreetRule::class => <<<'EOD'
Matches multiple street names to the customer's active shipping address street name. True if one street name matches.
EOD
    ,
    ShippingZipCodeRule::class => <<<'EOD'
Matches multiple zip codes to the customer's active shipping address zip code. True if one zip code matches.
EOD
    ,
    CustomerCustomFieldRule::class => <<<'EOD'
Matches if a customer has a specific custom field.
EOD
    ,
    AndRule::class => <<<'EOD'
Composition of rules. Matches if all match.
EOD
    ,
    NotRule::class => <<<'EOD'
Negates one rule.
EOD
    ,
    OrRule::class => <<<'EOD'
Composition of rules. Matches if at least one rule matches.
EOD
    ,
    XorRule::class => <<<'EOD'
Composition of rules. Matches if exactly one matches.
EOD
    ,
    DateRangeRule::class => <<<'EOD'
Match a fixed date range to now.
EOD
    ,
    SalesChannelRule::class => <<<'EOD'
Match a specific sales channel to the current context.
EOD
    ,
    TimeRangeRule::class => <<<'EOD'
Matches a fixed time range to now.
EOD
    ,
    WeekdayRule::class => <<<'EOD'
Matches a fixed day of the week to now.
EOD
    ,
    CurrencyRule::class => <<<'EOD'
Match a specific currency to the current context.
EOD
    ,
    LanguageRule::class => <<<'EOD'
Match a specific language to the current context.
EOD
    ,
    LineItemGoodsTotalRule::class => <<<'EOD'
Matches a total number of products in cart.
EOD
    ,
    OrderTotalAmountRule::class => <<<'EOD'
Matches a total amount of orders of the current customer.
EOD
    ,
];
