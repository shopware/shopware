<?php declare(strict_types=1);

namespace Shopware\Storefront\Account;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Account extends Bundle
{
    protected $name = 'Storefront/Account';

    public function getParent()
    {
        return 'Storefront';
    }
}
