<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * The PriceFacade is a wrapper around a price.
 *
 * @script-service cart_manipulation
 * @script-service product
 */
#[Package('checkout')]
class PriceFactory
{
    /**
     * @internal
     */
    public function __construct(private ScriptPriceStubs $stubs)
    {
    }

    /**
     * `create()` creates a new `PriceCollection` based on an array of prices.
     *
     * @param array<string, mixed> $prices The prices for the new collection, indexed by the currency-id or iso-code of the currency.
     *
     * @return PriceCollection Returns the newly created `PriceCollection`.
     *
     * @example add-absolute-surcharge/add-absolute-surcharge.twig 4 3 Create a new Price in the default currency.
     */
    public function create(array $prices): PriceCollection
    {
        return $this->stubs->build($prices);
    }
}
