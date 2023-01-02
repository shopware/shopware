<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cookie;

use Shopware\Core\Framework\Log\Package;
/**
 * @package storefront
 */
#[Package('storefront')]
interface CookieProviderInterface
{
    public function getCookieGroups(): array;
}
