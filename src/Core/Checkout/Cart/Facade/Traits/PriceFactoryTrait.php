<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\Services;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;

/**
 * @internal
 */
trait PriceFactoryTrait
{
    protected Services $services;

    public function create(array $price): PriceCollection
    {
        return $this->services->price($price);
    }
}
