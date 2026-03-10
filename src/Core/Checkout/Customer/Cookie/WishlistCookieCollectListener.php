<?php

declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Cookie;

use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('discovery')]
class WishlistCookieCollectListener
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function __invoke(CookieGroupCollectEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
        if (!$this->systemConfigService->getBool('core.cart.wishlistEnabled', $salesChannelId)) {
            return;
        }

        $comfortFeaturesCookieGroup = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES);
        if (!$comfortFeaturesCookieGroup) {
            return;
        }

        $entries = $comfortFeaturesCookieGroup->getEntries();
        if ($entries === null) {
            $entries = new CookieEntryCollection();
            $comfortFeaturesCookieGroup->setEntries($entries);
        }

        $entryWishlist = new CookieEntry('wishlist-enabled');
        $entryWishlist->name = 'cookie.groupComfortFeaturesWishlist';
        $entryWishlist->value = '1';
        $entryWishlist->expiration = 30;

        $entries->add($entryWishlist);
    }
}
