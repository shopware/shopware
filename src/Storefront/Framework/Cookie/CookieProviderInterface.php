<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cookie;

interface CookieProviderInterface
{
    public function getCookieGroups(): array;
}
