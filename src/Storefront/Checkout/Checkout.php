<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Checkout extends Bundle
{
    protected $name = 'Storefront/Checkout';

    public function getParent()
    {
        return 'Storefront';
    }
}
