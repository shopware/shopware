# Cart collector pattern

Sometimes it is neccessary to do multiple iterations before a valid and full calculated cart can be created.

This occurs if some elements have validation rules which can only be validated after the whole calculated cart is created.
For example, some shops have products which are called "premium products". This products can be added for free if the customer has reached a specified total amount in a cart. The validation of these products rely on a fully calculated cart.

In case that the cart has to be calculated multiple times, the data for a processor will be loaded multiple times too.
In order to prevent duplicate data queries to the storage, we defined the collector pattern. 

Collectors get cart access before the calculation loop starts. This pattern is implemented in the CartCalculator:
https://github.com/shopware/shopware/blob/labs/src/Cart/Cart/CartCalculator.php#L72

Each collector has to implement the `\Shopware\Cart\Cart\CollectorInterface` which expects the following functions to be implemented:

```php
<?php

namespace Shopware\Cart\Cart;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

interface CollectorInterface
{
    public function prepare(
        StructCollection $fetchDefinition,
        CartContainer $cartContainer,
        ShopContext $context
    ): void;

    public function fetch(
        StructCollection $dataCollection,
        StructCollection $fetchCollection,
        ShopContext $context
    ): void;
}
```

Before the data will be fetched by the `fetch` function, the cart calculator calls the `prepare` function of each collector.

The different collectors generically define the data needed from different sources (product line items, bundle line items, live shopping line items, etc.).

For example:
- The core product processor requires **validation** and **product data** for each line item of type `product`
- The plugin live shopping processor requires **validation**, **live shopping** and **product data** for each line item of type `liveShopping`

Both implementations can add a `\Shopware\Cart\Product\ProductFetchDefinition` to the `$fetchCollection` parameter which allows to merge this definitions and only execute a single
request to the `product data gateway`. 

The collector pattern provides two benefits:

1. Data of the same source can be merged into a single request for the data gateway
2. Data is only fetched once, no matter how many recalculation iterations there are
