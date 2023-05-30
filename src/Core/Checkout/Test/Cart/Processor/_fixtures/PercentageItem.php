<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Processor\_fixtures;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-ignore-next-line
 */
#[Package('checkout')]
class PercentageItem extends LineItem
{
    public function __construct(
        int $percentage,
        ?string $id = null
    ) {
        parent::__construct($id ?? Uuid::randomHex(), LineItem::DISCOUNT_LINE_ITEM);

        $this->priceDefinition = new PercentagePriceDefinition($percentage);
        $this->removable = true;
    }
}
