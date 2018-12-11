<?php declare(strict_types=1);

namespace Shopware\Storefront\Listing;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Listing extends Bundle
{
    protected $name = 'Storefront/Listing';

    public function getParent()
    {
        return 'Storefront';
    }
}
