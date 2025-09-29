<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Cookie;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\App\Cookie\AppCookieCollectListener;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class CookieGroupCollectListenerTest extends TestCase
{
    use AppSystemTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private AppCookieCollectListener $listener;

    protected function setUp(): void
    {
        $this->listener = new AppCookieCollectListener(static::getContainer()->get('app.repository'));
    }

    public function testSingleCookie(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/singleCookie');

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertCount(1, $groups);
        $firstGroup = $groups->first();
        static::assertNotNull($firstGroup);
        static::assertSame('swag.analytics.name', $firstGroup->name);
        static::assertSame('swag-analytics', $firstGroup->getCookie());
        static::assertSame('', $firstGroup->value);
        static::assertSame(30, $firstGroup->expiration);
    }

    public function testCookieGroup(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/cookieGroup');

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertCount(1, $groups);

        $firstGroup = $groups->first();
        static::assertNotNull($firstGroup);
        static::assertSame('app.cookies.group', $firstGroup->name);
        static::assertSame('app.cookies.group.description', $firstGroup->description);

        $entries = $firstGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(2, $entries);

        $firstCookie = $entries->get('swag-app-something');
        static::assertNotNull($firstCookie);
        static::assertSame('swag-app-something', $firstCookie->cookie);
        static::assertSame('first.cookie', $firstCookie->name);

        $secondCookie = $entries->get('swag-app-lorem-ipsum');
        static::assertNotNull($secondCookie);
        static::assertSame('swag-app-lorem-ipsum', $secondCookie->cookie);
        static::assertSame('second.cookie', $secondCookie->name);
    }

    public function testMergeCookiesWithCoreGroup(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/coreGroup');

        $coreCookieEntry = new CookieEntry('core.something');
        $coreCookieEntry->name = 'cookie.core';

        $coreCookieGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        $coreCookieGroup->setEntries(new CookieEntryCollection([$coreCookieEntry]));

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([$coreCookieGroup]),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertCount(1, $groups);

        $firstGroup = $groups->first();
        static::assertNotNull($firstGroup);
        static::assertSame(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED, $firstGroup->name);
        $entries = $firstGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(3, $entries);

        $coreCookieEntry = $entries->get('core.something');
        static::assertNotNull($coreCookieEntry);
        static::assertSame('core.something', $coreCookieEntry->cookie);
        static::assertSame('cookie.core', $coreCookieEntry->name);

        $firstCookie = $entries->get('swag-app-something');
        static::assertNotNull($firstCookie);
        static::assertSame('swag-app-something', $firstCookie->cookie);
        static::assertSame('first.something', $firstCookie->name);

        $secondCookie = $entries->get('swag-app-lorem-ipsum');
        static::assertNotNull($secondCookie);
        static::assertSame('swag-app-lorem-ipsum', $secondCookie->cookie);
        static::assertSame('second.lorem.ipsum', $secondCookie->name);
    }

    public function testMergeCookiesFromMultipleApps(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/mergeAppGroups');

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertCount(1, $groups);

        $firstGroup = $groups->first();
        static::assertNotNull($firstGroup);
        static::assertSame('app.cookie.group.name', $firstGroup->name);
        $entries = $firstGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(3, $entries);

        $firstAppFirstCookie = $entries->get('swag-app-something');
        static::assertNotNull($firstAppFirstCookie);
        static::assertSame('swag-app-something', $firstAppFirstCookie->cookie);
        static::assertSame('first.something', $firstAppFirstCookie->name);

        $firstAppSecondCookie = $entries->get('swag-app-lorem-ipsum');
        static::assertNotNull($firstAppSecondCookie);
        static::assertSame('swag-app-lorem-ipsum', $firstAppSecondCookie->cookie);
        static::assertSame('second.lorem.ipsum', $firstAppSecondCookie->name);

        $secondAppCookie = $entries->get('swag-app-foobar');
        static::assertNotNull($secondAppCookie);
        static::assertSame('swag-app-foobar', $secondAppCookie->cookie);
        static::assertSame('other.app.foobar', $secondAppCookie->name);
    }

    public function testItIgnoresDeactivatedApps(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/singleCookie', false);

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertEmpty($groups);
    }
}
