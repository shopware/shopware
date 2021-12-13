<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\CartFacadeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;

trait PriceFactoryTrait
{
    private CartFacadeHelper $helper;

    public function create(array $price): PriceCollection
    {
        return $this->helper->price($price);
    }
}
