<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\TaxProvider\Struct;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('checkout')]
class TaxProviderResult extends Struct
{
    /**
     * @param array<string, CalculatedTaxCollection>|null $lineItemTaxes
     * @param array<string, CalculatedTaxCollection>|null $deliveryTaxes
     */
    public function __construct(
        protected ?array $lineItemTaxes = null,
        protected ?array $deliveryTaxes = null,
        protected ?CalculatedTaxCollection $cartPriceTaxes = null
    ) {
    }

    /**
     * @return array<string, CalculatedTaxCollection>|null
     */
    public function getLineItemTaxes(): ?array
    {
        return $this->lineItemTaxes;
    }

    /**
     * @return array<string, CalculatedTaxCollection>|null
     */
    public function getDeliveryTaxes(): ?array
    {
        return $this->deliveryTaxes;
    }

    public function getCartPriceTaxes(): ?CalculatedTaxCollection
    {
        return $this->cartPriceTaxes;
    }

    public function declaresTaxes(): bool
    {
        return $this->lineItemTaxes
            || $this->deliveryTaxes
            || ($this->cartPriceTaxes && $this->cartPriceTaxes->count() > 0);
    }
}
