[titleEn]: <>(Delivery)
[titleDe]: <>(Delivery)
[wikiUrl]: <>(../checkout/delivery?category=shopware-platform-en/checkout)

Line items can contain `Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation` 
which are used to determine the timeframe in which a line item and the whole order will
be delivered to the customer. Shopware supports split deliveries based on different criteria. 

If an item is out of stock or only partially in stock, Shopware might create two deliveries.

The `DeliveryInformation` contains the following properties:

```php
<?php

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Framework\Struct\Struct;

class DeliveryInformation extends Struct
{
    /**
     * @var int
     */
    protected $availableStock;

    /**
     * @var float
     */
    protected $weight;

    /**
     * @var DeliveryDate
     */
    protected $inStockDeliveryDate;

    /**
     * @var DeliveryDate
     */
    protected $outOfStockDeliveryDate;
}
```

- `availableStock`: the quantity which is currently in stock.
- `weight`: weight of the line item in kg
- `inStockDeliveryDate`: `Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate` object
which contains the earliest and latest in stock delivery date as `DateTime`
- `outOfStockDeliveryDate`: `DeliveryDate` object
which contains the earliest and latest in stock delivery date as `DateTime`

Based on this information, the `Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder` will split the order
into different `Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery`.

```php
<?php

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Struct\Struct;

class Delivery extends Struct
{
    /**
     * @var DeliveryPositionCollection
     */
    protected $positions;

    /**
     * @var ShippingLocation
     */
    protected $location;

    /**
     * @var DeliveryDate
     */
    protected $deliveryDate;

    /**
     * @var ShippingMethodEntity
     */
    protected $shippingMethod;

    /**
     * @var CalculatedPrice
     */
    protected $shippingCosts;

    /**
     * @var DeliveryDate
     */
    protected $endDeliveryDate;
    
}
```

The `DeliveryPositionCollection` contains a list of `DeliveryPosition`:

```php
<?php

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Struct\Struct;

class DeliveryPosition extends Struct
{
    /**
     * @var LineItem
     */
    protected $lineItem;

    /**
     * @var float
     */
    protected $quantity;

    /**
     * @var Price
     */
    protected $price;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var DeliveryDate
     */
    protected $deliveryDate;

}
```
After a delivery has been processed by the `DeliveryBuilder`, 
the `Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator` calculates the shipping costs.

The shipping costs can be calculated by weight, price, line item count or 
you can implement your own logic.