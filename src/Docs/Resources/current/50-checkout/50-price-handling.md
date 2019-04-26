[titleEn]: <>(Price handling)
[titleDe]: <>(Price handling)
[wikiUrl]: <>(../checkout/price-handling?category=shopware-platform-en/checkout)

Shopware offers three different ways to define how a price of a line item will be calculated. 
The options are called price definitions. If you use the line items provided 
by Shopware (product, voucher, discount), you normally don't have to worry about the price definitions.


## Price definitions
All price definitions have the `Core/Checkout/Cart/Price/Struct/PriceDefinitionInterface` in common.


### QuantityPriceDefinition

This price definition is based on the quantity and probably the most common. The price will automatically change
if the quantity of the line item changes.

```php
<?php
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

$taxRules = new TaxRuleCollection([
    new TaxRule(19),
]);

$priceDefinition = new QuantityPriceDefinition(12.99, $taxRules);
```

In the example above a QuantityPriceDefinition with an amount of 12,99 and a tax rate of 19% 
has been defined.
The price definition has two additional parameters which are optional. 
The first one defines the quantity and is set to 1 by default. 
The second one defines if the prices are already calculated. 
By default its set to false, which means the tax rules are not reflected in the price and 
the calculator will add the taxes during the calculation process.


### AbsolutePriceDefinition

This price definition is static and will not change based on quantity.

```php
<?php
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;

$priceDefinition = new AbsolutePriceDefinition(12.99);
```

### PercentagePriceDefinition

This price definition is based on a percentage and commonly used for percental discounts or surcharges.

```php
<?php
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;

$priceDefinition = new PercentagePriceDefinition(10);
```

If you add the price definition above to a line item in the cart, 
the price of this line item will be dynamically calculated based on the total price of the other line items.

Please be aware that the example above would be a surcharge. 
If you want to create a discount, use a negative percentage.

You can also restrict the PercentagePriceDefinition by using the second `rule` parameter.

```php
<?php
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Content\Product\Cart\ProductCollector;

$productRule = (new LineItemOfTypeRule())->assign(['lineItemType' => LineItem::PRODUCT_LINE_ITEM_TYPE]);
$priceDefinition = new PercentagePriceDefinition(10, $productRule);
```
Example: Restrict surcharge to products.

```php
<?php
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Framework\Rule\Rule;

$orderAmountRule = (new CartAmountRule())->assign(['amount' => 100, 'operator' => Rule::OPERATOR_LTE]);
$priceDefinition = new PercentagePriceDefinition(10, $orderAmountRule);
```
Example: Restrict surcharge to carts with a total amount of 100 or less.

## Extensibility concept
All services above are defined inside the service container, which means each service can be replaced or decorated. 
