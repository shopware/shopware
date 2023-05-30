<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Processor\_fixtures;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-ignore-next-line
 */
#[Package('checkout')]
class ContainerItem extends LineItem
{
    /**
     * @param array<LineItem> $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct(Uuid::randomHex(), LineItem::CONTAINER_LINE_ITEM);

        $this->children = new LineItemCollection($items);

        $this->removable = true;
        $this->good = true;
    }
}
