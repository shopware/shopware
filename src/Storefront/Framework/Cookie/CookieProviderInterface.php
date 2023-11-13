<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cookie;

use Shopware\Core\Framework\Log\Package;

#[Package('storefront')]
interface CookieProviderInterface
{
    /**
     * @return array<string|int, mixed>
     */
    public function getCookieGroups(): array;
}
