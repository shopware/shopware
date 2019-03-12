<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Theme;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Theme extends Bundle
{
    protected $name = 'Storefront/Framework/Theme';

    public function getParent(): string
    {
        return 'Storefront/Framework';
    }
}
