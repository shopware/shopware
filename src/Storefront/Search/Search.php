<?php declare(strict_types=1);

namespace Shopware\Storefront\Search;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Search extends Bundle
{
    protected $name = 'Storefront/Search';

    public function getParent()
    {
        return 'Storefront';
    }
}
