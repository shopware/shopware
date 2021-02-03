<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidPriceFieldTypeException;

class PriceDefinitionFactory
{
    public function factory(Context $context, array $priceDefinition, string $lineItemType): PriceDefinitionInterface
    {
        if (!isset($priceDefinition['type'])) {
            throw new InvalidPriceFieldTypeException('none');
        }

        switch ($priceDefinition['type']) {
            case QuantityPriceDefinition::TYPE:
                return QuantityPriceDefinition::fromArray($priceDefinition);
            case AbsolutePriceDefinition::TYPE:
                return new AbsolutePriceDefinition((float) $priceDefinition['price']);
            case PercentagePriceDefinition::TYPE:
                return new PercentagePriceDefinition($priceDefinition['percentage']);
        }

        throw new InvalidPriceFieldTypeException($priceDefinition['type']);
    }
}
