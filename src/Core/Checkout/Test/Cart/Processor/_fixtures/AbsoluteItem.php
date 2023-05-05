<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Processor\_fixtures;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CurrencyPriceDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-ignore-next-line
 */
#[Package('checkout')]
class AbsoluteItem extends LineItem
{
    public function __construct(
        float $price,
        ?string $id = null
    ) {
        parent::__construct($id ?? Uuid::randomHex(), LineItem::DISCOUNT_LINE_ITEM);

        $this->priceDefinition = new CurrencyPriceDefinition(new PriceCollection([
            new Price(Defaults::CURRENCY, $price, $price, false),
        ]));
        $this->removable = true;
    }
}
