[titleEn]: <>(Transaction)
[wikiUrl]: <>(../checkout/tax?category=shopware-platform-en/checkout)

Shopware provides a comprehensive tax system. It allows you to define multiple tax definitions 
and also supports partial taxes. Taxes are defined as rules which allow to recalculate 
the taxes if the price of a line item changes.


A price definition contains a TaxRuleCollection and a price contains a CalculatedTaxRuleCollection.

## Tax rule
`Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule` defines a partial tax rate. 
It contains the tax rate as float and the percentage which should be used for calculation.

```php
<?php
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

$firstTaxRule = new PercentageTaxRule(19, 80);
$secondTaxRule = new PercentageTaxRule(7, 20);

$taxRules = new TaxRuleCollection([$firstTaxRule, $secondTaxRule]);
```

