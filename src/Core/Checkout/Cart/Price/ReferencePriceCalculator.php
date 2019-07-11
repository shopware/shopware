<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;

class ReferencePriceCalculator
{
    /**
     * @var PriceRoundingInterface
     */
    private $priceRounding;

    public function __construct(PriceRoundingInterface $priceRounding)
    {
        $this->priceRounding = $priceRounding;
    }

    public function calculate(float $price, QuantityPriceDefinition $quantityPriceDefinition): ?ReferencePrice
    {
        if (!$quantityPriceDefinition->getReferencePriceDefinition()) {
            return null;
        }

        $price = $price / $quantityPriceDefinition->getReferencePriceDefinition()->getPurchaseUnit() * $quantityPriceDefinition->getReferencePriceDefinition()->getReferenceUnit();
        $price = $this->priceRounding->round($price, $quantityPriceDefinition->getPrecision());

        return new ReferencePrice(
            $price,
            $quantityPriceDefinition->getReferencePriceDefinition()->getPurchaseUnit(),
            $quantityPriceDefinition->getReferencePriceDefinition()->getReferenceUnit(),
            $quantityPriceDefinition->getReferencePriceDefinition()->getUnitName()
        );
    }
}
