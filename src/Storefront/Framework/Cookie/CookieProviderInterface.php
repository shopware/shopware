<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cookie;

/**
 * @package storefront
 */
interface CookieProviderInterface
{
    public function getCookieGroups(): array;
}
