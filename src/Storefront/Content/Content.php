<?php declare(strict_types=1);

namespace Shopware\Storefront\Content;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Content extends Bundle
{
    protected $name = 'Storefront/Content';

    public function getParent()
    {
        return 'Storefront';
    }
}
