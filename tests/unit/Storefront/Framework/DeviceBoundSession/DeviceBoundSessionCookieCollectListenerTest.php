<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\DeviceBoundSession;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionCookieCollectListener;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeviceBoundSessionCookieCollectListener::class)]
class DeviceBoundSessionCookieCollectListenerTest extends TestCase
{
    public function testCookieIsListedAsTechnicallyRequired(): void
    {
        $event = $this->event($this->requiredGroup());

        (new DeviceBoundSessionCookieCollectListener(true))($event);

        $entry = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED)?->getEntries()?->get('sw-dbsc-*');
        static::assertNotNull($entry);
        static::assertSame('cookie.groupRequiredDeviceBoundSession', $entry->name);
        static::assertFalse(isset($entry->value), 'the consent manager must not set the cookie itself');
    }

    public function testCookieIsNotListedWhenDisabled(): void
    {
        $event = $this->event($this->requiredGroup());

        (new DeviceBoundSessionCookieCollectListener(false))($event);

        static::assertNull($event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED)?->getEntries());
    }

    public function testCookieIsNotAddedToAnOptionalGroup(): void
    {
        $group = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        $event = $this->event($group);

        (new DeviceBoundSessionCookieCollectListener(true))($event);

        static::assertNull($group->getEntries());
    }

    public function testNothingHappensWithoutRequiredGroup(): void
    {
        $event = $this->event(new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING));

        (new DeviceBoundSessionCookieCollectListener(true))($event);

        static::assertNull($event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED));
    }

    private function requiredGroup(): CookieGroup
    {
        $group = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        $group->isRequired = true;

        return $group;
    }

    private function event(CookieGroup $group): CookieGroupCollectEvent
    {
        return new CookieGroupCollectEvent(new CookieGroupCollection([$group]), new Request(), Generator::generateSalesChannelContext());
    }
}
