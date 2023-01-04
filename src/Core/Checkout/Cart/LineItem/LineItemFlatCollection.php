<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<LineItem>
 */
#[Package('checkout')]
class LineItemFlatCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'cart_line_item_flat_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return LineItem::class;
    }
}
