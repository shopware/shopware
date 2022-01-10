<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\CartFacadeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;

trait PriceFactoryTrait
{
    private CartFacadeHelper $helper;

    /**
     * `create()` creates a new `PriceCollection` based on an array of prices.
     *
     * @param array $price The prices for the new collection, indexed by the currency-id or iso-code of the currency.
     *
     * @return PriceCollection Returns the newly created `PriceCollection`.
     */
    public function create(array $price): PriceCollection
    {
        return $this->helper->price($price);
    }
}
