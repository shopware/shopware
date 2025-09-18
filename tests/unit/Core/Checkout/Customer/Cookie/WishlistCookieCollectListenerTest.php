<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Cookie\WishlistCookieCollectListener;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(WishlistCookieCollectListener::class)]
class WishlistCookieCollectListenerTest extends TestCase
{
    private StaticSystemConfigService $systemConfigService;

    private WishlistCookieCollectListener $listener;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService(['core.cart.wishlistEnabled' => true]);
        $this->listener = new WishlistCookieCollectListener($this->systemConfigService);
    }

    public function testWishlistConfigNotActive(): void
    {
        $this->systemConfigService->set('core.cart.wishlistEnabled', false);

        /** @phpstan-ignore shopware.mockingSimpleObjects (A mock is used here to ensure that the method is not called) */
        $cookieCollection = $this->createMock(CookieGroupCollection::class);
        $cookieCollection->expects($this->never())->method('get');
        $event = new CookieGroupCollectEvent($cookieCollection, Generator::generateSalesChannelContext());

        $this->listener->__invoke($event);
    }

    public function testComfortFeaturesCookieGroupNotPresent(): void
    {
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([new CookieGroup('test')]),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        static::assertCount(1, $event->cookieGroupCollection);
    }

    public function testWishlistCookieIsAdded(): void
    {
        $cookieGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES);

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([$cookieGroup]),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        $wishlistCookie = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES)?->getEntries()?->get('wishlist-enabled');
        static::assertNotNull($wishlistCookie);
    }
}
