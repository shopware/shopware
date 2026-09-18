<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\SelectedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractPriceSelector
{
    abstract public function getDecorated(): AbstractPriceSelector;

    /**
     * @param TaxRuleCollection $taxRules the tax rules that apply to the price, used to derive a value the stored price does not provide
     */
    abstract public function select(Price $price, TaxRuleCollection $taxRules, SalesChannelContext $context): SelectedPrice;
}
