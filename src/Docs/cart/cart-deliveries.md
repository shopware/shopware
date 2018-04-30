# Cart deliveries

The cart process allows to split a single cart into different deliveries. The conditions to split deliveries are currently only the stock availability of an line item.

The new cart representation allows the seperation of line items into seperate deliveries. The `\Shopware\Cart\Delivery\DeliverySeparatorProcessor` is concerned with the seperation of deliveries, with respect to stock availabilities and shipping times of the ordered products. The `\Shopware\Cart\Delivery\DeliveryCalculatorProcessor` eventually calculated the shipping costs per delivery.

Each delivery has a determined delivery date which contains an earliest delivery and latest delivery time:
```php
<?php

namespace Shopware\Cart\Delivery;

use Shopware\Framework\Struct\Struct;

class DeliveryDate extends Struct
{
    /**
     * @var \DateTime
     */
    protected $earliest;

    /**
     * @var \DateTime
     */
    protected $latest;
}
```
*Note, code examples have been simplified for clarity*

In addition to DeliveryDate, a ShippingLocation and a shipping method are defined for each delivery.

```php
<?php

namespace Shopware\Cart\Delivery;
use Shopware\Framework\Struct\Struct;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;

class Delivery extends Struct
{
    /**
     * @var ShippingLocation
     */
    protected $location;

    /**
     * @var DeliveryDate
     */
    protected $deliveryDate;

    /**
     * @var ShippingMethodBasicStruct
     */
    protected $shippingMethod;
    
    public function getLocation(): ShippingLocation
    {
        return $this->location;
    }

    public function getDeliveryDate(): DeliveryDate
    {
        return $this->deliveryDate;
    }

    public function getShippingMethod(): ShippingMethodBasicStruct
    {
        return $this->shippingMethod;
    }
}
```

All calculated deliveries are referenced in the `\Shopware\Cart\Cart\CalculatedCart`.
```php
<?php

namespace Shopware\Cart\Cart;
use Shopware\Cart\Delivery\DeliveryCollection;
use Shopware\Framework\Struct\Struct;

class CalculatedCart extends Struct
{
    /**
     * @var DeliveryCollection
     */
    protected $deliveries;

    public function getDeliveries(): DeliveryCollection
    {
        return $this->deliveries;
    }
}
```

Additionally to the location, date and shipping method the delivery contains a list of all deliverable line items:
```php
<?php

namespace Shopware\Cart\Delivery;
use Shopware\Framework\Struct\Struct;

class Delivery extends Struct
{
    /**
     * @var DeliveryPositionCollection
     */
    protected $positions;
    
    public function getPositions(): DeliveryPositionCollection
    {
        return $this->positions;
    }
}
```

Unlike a voucher, most products in a shopping cart are goods that need to be delivered (with few exceptions - esd, ...).
In order to mark an element for the cart as an deliverable element, it is necessary to implement the interface `\Shopware\Cart\LineItem\DeliverableLineItemInterface`.
These interface provides all functions which are required to determine the delivery time and to calculate the shipping costs:
```php
<?php
declare(strict_types=1);

namespace Shopware\Cart\LineItem;

use Shopware\Cart\Delivery\Delivery;
use Shopware\Cart\Delivery\DeliveryDate;

interface DeliverableLineItemInterface extends CalculatedLineItemInterface
{
    public function getStock(): int;

    public function getWeight(): float;

    public function getInStockDeliveryDate(): DeliveryDate;

    public function getOutOfStockDeliveryDate(): DeliveryDate;
}
```

