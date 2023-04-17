<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
trait PriceFactoryTrait
{
    protected ScriptPriceStubs $priceStubs;

    // @param array<string, array{gross:float, net:float, linked?:bool}> $price The prices for the new collection, indexed by the currency-id or iso-code of the currency.

    /**
     * `create()` creates a new `PriceCollection` based on an array of prices.
     *
     * @param array<string, mixed> $price The prices for the new collection, indexed by the currency-id or iso-code of the currency.
     *
     * @return PriceCollection Returns the newly created `PriceCollection`.
     *
     * @example add-absolute-surcharge/add-absolute-surcharge.twig 4 3 Create a new Price in the default currency.
     */
    public function create(array $price): PriceCollection
    {
        return $this->priceStubs->build($price);
    }
}
