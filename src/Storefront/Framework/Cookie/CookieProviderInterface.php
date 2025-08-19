<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cookie;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0, replaced by the CookieCollectionProviderInterface
 */
interface CookieProviderInterface
{
    /**
     * @return array<string|int, mixed>
     */
    public function getCookieGroups(): array;
}
