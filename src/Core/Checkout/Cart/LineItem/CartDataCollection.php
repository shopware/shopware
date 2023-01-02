<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<mixed>
 */
#[Package('checkout')]
class CartDataCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'cart_data_collection';
    }
}
