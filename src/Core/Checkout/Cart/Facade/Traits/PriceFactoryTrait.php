<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\CartFacadeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;

/**
 * @internal The trait is not intended for re-usability in other domains
 */
trait PriceFactoryTrait
{
    protected CartFacadeHelper $services;

    /**
     * @public-api used for app scripting
     */
    public function create(array $price): PriceCollection
    {
        return $this->services->price($price);
    }
}
