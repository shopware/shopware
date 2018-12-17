<?php declare(strict_types=1);

namespace Shopware\Storefront\Product;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Product extends Bundle
{
    protected $name = 'Storefront/Product';

    public function getParent()
    {
        return 'Storefront';
    }
}
