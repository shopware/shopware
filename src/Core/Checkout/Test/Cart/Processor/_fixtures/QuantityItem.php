<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Processor\_fixtures;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-ignore-next-line
 */
#[Package('checkout')]
class QuantityItem extends LineItem
{
    public function __construct(
        float $price,
        TaxRuleCollection $taxes,
        bool $good = true,
        int $quantity = 1
    ) {
        parent::__construct(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, null, $quantity);

        $this->priceDefinition = new QuantityPriceDefinition($price, $taxes, $quantity);
        $this->setGood($good);
    }
}
