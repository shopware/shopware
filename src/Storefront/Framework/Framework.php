<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Framework extends Bundle
{
    protected $name = 'Storefront/Framework';

    public function getParent()
    {
        return 'Storefront';
    }
}
