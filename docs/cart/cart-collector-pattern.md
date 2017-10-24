# Cart collector pattern

To calculate a cart, sometimes it is required to calculate and validate a cart multiple times before a valid and full calculated cart can be created.
This occurs if some elements has validation rules which can only be validated after the whole calculated cart is created.
For example, some shops has products which called "premium products". This products can be added for free if the customer
has reached a specified total amount in a cart. This products can first be validated when the full cart process procceed.
In case that the cart has to be calculated multiple times, the data for an processor will be loaded multiple times too.
To prevent duplicate data queries to the storage, the cart provides a collector pattern. 

Collectors gets cart access before the calculation loop starts. This pattern is implemented in the CartCalculator:
https://github.com/shopware/shopware/blob/labs/src/Cart/Cart/CartCalculator.php#L72

Each collector has to implement the `\Shopware\Cart\Cart\CollectorInterface` which implements the following functions:

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
This different collectors, which handles different data sources (product line items, bundle line items, live shopping line items, ...) to fetch shared data.
For example:
- The core product processor requires **validation** and **product data** for each line item of type `product`
- The plugin live shopping processor requires **validation**, **live shopping** and **product data** foreach each line item of type `liveShopping`

Both implementation can add a `\Shopware\Cart\Product\ProductFetchDefinition` to the `$fetchCollection` parameter which allows to merge this definitions and only execute a single
request to the `product data gateway`. 

The collector pattern provides two benefits:

1. Data of the same source, can be merged into a single request for the data gateway
2. Data are only fetched one time, no matter how many times a cart has to recalculate
 