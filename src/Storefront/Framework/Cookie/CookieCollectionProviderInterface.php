<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cookie;

use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
interface CookieCollectionProviderInterface
{
    public function getCookieGroupCollection(): CookieGroupCollection;
}
