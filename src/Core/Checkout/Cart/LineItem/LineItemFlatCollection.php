<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Framework\Struct\Collection;

/**
 * This class can be used as a type-safe list of LineItem objects.
 * In contrast to the original LineItemCollection it allows you
 * to have the same line item objects multiple times in the list,
 * without bundling them together using a single line item id/key.
 *
 * @method LineItem[]    getIterator()
 * @method LineItem[]    getElements()
 * @method LineItem|null first()
 * @method LineItem|null last()
 */
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
