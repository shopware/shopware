<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cookie;

use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use {@see CookieGroupCollectEvent} instead to introduce cookies.
 */
class CookieProvider implements CookieProviderInterface
{
    /**
     * @internal
     */
    public function __construct()
    {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use {@see CookieGroupCollectEvent} instead to introduce cookies.
     */
    public function getCookieGroups(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use CookieGroupCollectEvent instead to introduce cookies')
        );

        return [
        ];
    }
}
