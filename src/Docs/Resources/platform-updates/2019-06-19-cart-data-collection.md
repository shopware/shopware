[titleEn]: <>(Cart data collection)

In connection with the new processor pattern of the cart, we had to replace the `StructCollection` with a `CartDataCollection`.

As a result, the following interfaces have changed:
* `\Shopware\Core\Checkout\Cart\CartDataCollectorInterface`
* `\Shopware\Core\Checkout\Cart\CartProcessorInterface`

```
<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CartDataCollectorInterface
{
    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void;
}
```

```
<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CartProcessorInterface
{
    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void;
}

```
Furthermore we removed the old interface `Shopware\Core\Checkout\Cart\CollectorInterface` which is no longer used.