<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PriceDefinitionFactory
{
    /**
     * @param array{type:string, price:float, percentage:float} $priceDefinition
     */
    public function factory(Context $context, array $priceDefinition, string $lineItemType): PriceDefinitionInterface
    {
        if (!isset($priceDefinition['type'])) {
            throw CartException::invalidPriceFieldType('none');
        }

        return match ($priceDefinition['type']) {
            QuantityPriceDefinition::TYPE => QuantityPriceDefinition::fromArray($priceDefinition),
            AbsolutePriceDefinition::TYPE => new AbsolutePriceDefinition((float) $priceDefinition['price']),
            PercentagePriceDefinition::TYPE => new PercentagePriceDefinition($priceDefinition['percentage']),
            default => throw CartException::invalidPriceFieldType($priceDefinition['type']),
        };
    }
}
