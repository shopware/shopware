<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(CookieProvider::class)]
class CookieProviderTest extends TestCase
{
    public function testGetCookieGroups(): void
    {
        $eventDispatcher = new CollectingEventDispatcher();
        $cookieGroups = (new CookieProvider($eventDispatcher, ['name' => 'test-session-name-']))->getCookieGroups(Generator::generateSalesChannelContext());

        $events = $eventDispatcher->getEvents();
        static::assertCount(1, $events);
        $collectEvent = $events[0];
        static::assertInstanceOf(CookieGroupCollectEvent::class, $collectEvent);
        static::assertSame($cookieGroups, $collectEvent->cookieGroupCollection);

        static::assertCount(4, $cookieGroups);

        $requiredGroup = $cookieGroups->get('cookie.groupRequired');
        static::assertInstanceOf(CookieGroup::class, $requiredGroup);
        static::assertNotNull($requiredGroup->getEntries());
        static::assertCount(4, $requiredGroup->getEntries());

        $statisticalGroup = $cookieGroups->get('cookie.groupStatistical');
        static::assertInstanceOf(CookieGroup::class, $statisticalGroup);
        static::assertNotNull($statisticalGroup->getEntries());
        static::assertCount(1, $statisticalGroup->getEntries());

        $comfortFeaturesGroup = $cookieGroups->get('cookie.groupComfortFeatures');
        static::assertInstanceOf(CookieGroup::class, $comfortFeaturesGroup);
        static::assertNotNull($comfortFeaturesGroup->getEntries());
        static::assertCount(2, $comfortFeaturesGroup->getEntries());

        $marketingGroup = $cookieGroups->get('cookie.groupMarketing');
        static::assertInstanceOf(CookieGroup::class, $marketingGroup);
        static::assertNotNull($marketingGroup->getEntries());
        static::assertCount(1, $marketingGroup->getEntries());
    }

    public function testNewCookieAddedViaEvent(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(CookieGroupCollectEvent::class, static function (CookieGroupCollectEvent $event): void {
            $event->cookieGroupCollection->add(new CookieGroup('test'));
        });

        $cookieGroups = (new CookieProvider($eventDispatcher, ['name' => 'test-session-name-']))->getCookieGroups(Generator::generateSalesChannelContext());
        static::assertCount(5, $cookieGroups);

        $testGroup = $cookieGroups->get('test');
        static::assertInstanceOf(CookieGroup::class, $testGroup);
    }
}
