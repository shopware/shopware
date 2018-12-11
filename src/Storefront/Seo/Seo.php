<?php declare(strict_types=1);

namespace Shopware\Storefront\Seo;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Seo extends Bundle
{
    protected $name = 'Storefront/Seo';

    public function getParent()
    {
        return 'Storefront';
    }
}
