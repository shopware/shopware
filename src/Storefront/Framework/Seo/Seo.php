<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Seo extends Bundle
{
    protected $name = 'Storefront/Framework/Seo';

    public function getParent()
    {
        return 'Storefront';
    }
}
