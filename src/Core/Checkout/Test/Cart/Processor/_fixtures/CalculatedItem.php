<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Processor\_fixtures;

use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class CalculatedItem extends QuantityItem
{
    public function __construct(
        float $price,
        TaxRuleCollection $taxes,
        SalesChannelContext $context,
        bool $good = true,
        int $quantity = 1
    ) {
        parent::__construct($price, $taxes, $good, $quantity);

        $calculator = new QuantityPriceCalculator(
            new GrossPriceCalculator(new TaxCalculator(), new CashRounding()),
            new NetPriceCalculator(new TaxCalculator(), new CashRounding())
        );

        \assert($this->getPriceDefinition() instanceof QuantityPriceDefinition);
        $this->price = $calculator->calculate($this->getPriceDefinition(), $context);
    }
}
