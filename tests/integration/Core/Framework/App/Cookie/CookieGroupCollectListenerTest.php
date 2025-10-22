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

    public function testCookieGroupRedirectionWithDefaultTargetGroup(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/redirectWithDefaultTargetGroup');

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertCount(1, $groups);

        // Should be redirected to Statistical group via default-target-group
        $statisticalGroup = $groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        static::assertNotNull($statisticalGroup);
        static::assertSame(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL, $statisticalGroup->name);

        $entries = $statisticalGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(2, $entries);

        $pageviewCookie = $entries->get('testapp-analytics-pageview');
        static::assertNotNull($pageviewCookie);
        static::assertSame('testapp-analytics-pageview', $pageviewCookie->cookie);
        static::assertSame('testapp.analytics.pageview', $pageviewCookie->name);

        $sessionCookie = $entries->get('testapp-analytics-session');
        static::assertNotNull($sessionCookie);
        static::assertSame('testapp-analytics-session', $sessionCookie->cookie);
        static::assertSame('testapp.analytics.session', $sessionCookie->name);
    }

    public function testCookieGroupRedirectionWithTargetGroupOverride(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/redirectWithTargetGroup');

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertCount(2, $groups);

        // First group should be redirected to Statistical (target-group overrides default)
        $statisticalGroup = $groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        static::assertNotNull($statisticalGroup);

        $statisticalEntries = $statisticalGroup->getEntries();
        static::assertNotNull($statisticalEntries);
        static::assertCount(1, $statisticalEntries);

        $analyticsCookie = $statisticalEntries->get('testapp-analytics-pageview');
        static::assertNotNull($analyticsCookie);
        static::assertSame('testapp-analytics-pageview', $analyticsCookie->cookie);
        static::assertSame('testapp.analytics.pageview', $analyticsCookie->name);

        // Second group should be redirected to Marketing (default-target-group)
        $marketingGroup = $groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING);
        static::assertNotNull($marketingGroup);

        $marketingEntries = $marketingGroup->getEntries();
        static::assertNotNull($marketingEntries);
        static::assertCount(1, $marketingEntries);

        $trackingCookie = $marketingEntries->get('testapp-tracking-conversion');
        static::assertNotNull($trackingCookie);
        static::assertSame('testapp-tracking-conversion', $trackingCookie->cookie);
        static::assertSame('testapp.tracking.conversion', $trackingCookie->name);
    }

    public function testCookieGroupRedirectionMergesWithExistingCoreGroup(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/redirectWithDefaultTargetGroup');

        // Simulate existing core cookies in the Statistical group
        $coreCookieEntry = new CookieEntry('core-analytics');
        $coreCookieEntry->name = 'cookie.core.analytics';

        $statisticalGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        $statisticalGroup->setEntries(new CookieEntryCollection([$coreCookieEntry]));

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([$statisticalGroup]),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertCount(1, $groups);

        $statisticalGroup = $groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        static::assertNotNull($statisticalGroup);

        $entries = $statisticalGroup->getEntries();
        static::assertNotNull($entries);
        // Should have 3 cookies: 1 from core + 2 from app
        static::assertCount(3, $entries);

        // Core cookie should still be there
        $coreCookie = $entries->get('core-analytics');
        static::assertNotNull($coreCookie);
        static::assertSame('core-analytics', $coreCookie->cookie);

        // App cookies should be merged in
        $appPageviewCookie = $entries->get('testapp-analytics-pageview');
        static::assertNotNull($appPageviewCookie);
        static::assertSame('testapp-analytics-pageview', $appPageviewCookie->cookie);

        $appSessionCookie = $entries->get('testapp-analytics-session');
        static::assertNotNull($appSessionCookie);
        static::assertSame('testapp-analytics-session', $appSessionCookie->cookie);
    }
}
