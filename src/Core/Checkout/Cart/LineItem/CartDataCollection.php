<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @package checkout
 *
 * @extends Collection<mixed>
 */
class CartDataCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'cart_data_collection';
    }
}
