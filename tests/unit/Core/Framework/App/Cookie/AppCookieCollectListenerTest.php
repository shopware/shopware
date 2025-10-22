<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Cookie\AppCookieCollectListener;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @phpstan-import-type Cookie from AppEntity
 */
#[CoversClass(AppCookieCollectListener::class)]
class AppCookieCollectListenerTest extends TestCase
{
    public function testSingleCookie(): void
    {
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $appEntity = $this->createAppEntity(Uuid::randomHex(), [
            [
                'value' => '',
                'cookie' => 'swag-analytics',
                'expiration' => '30',
                'snippet_name' => 'swag.analytics.name',
            ],
        ]);
        $this->createListener($appEntity)->__invoke($event);

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
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $appEntity = $this->createAppEntity(Uuid::randomHex(), [
            [
                'entries' => [
                    [
                        'cookie' => 'swag-app-something',
                        'snippet_name' => 'first.cookie',
                        'snippet_description' => 'first.cookie.description',
                        'value' => 'test',
                        'expiration' => '30',
                    ],
                    [
                        'cookie' => 'swag-app-lorem-ipsum',
                        'snippet_name' => 'second.cookie',
                    ],
                ],
                'snippet_name' => 'app.cookies.group',
                'snippet_description' => 'app.cookies.group.description',
            ],
        ]);
        $this->createListener($appEntity)->__invoke($event);

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
        static::assertSame('first.cookie.description', $firstCookie->description);
        static::assertSame('test', $firstCookie->value);
        static::assertSame(30, $firstCookie->expiration);

        $secondCookie = $entries->get('swag-app-lorem-ipsum');
        static::assertNotNull($secondCookie);
        static::assertSame('swag-app-lorem-ipsum', $secondCookie->cookie);
        static::assertSame('second.cookie', $secondCookie->name);
    }

    public function testMergeCookiesWithCoreGroup(): void
    {
        $coreCookieEntry = new CookieEntry('core.something');
        $coreCookieEntry->name = 'cookie.core';

        $coreCookieGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        $coreCookieGroup->setEntries(new CookieEntryCollection([$coreCookieEntry]));

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([$coreCookieGroup]),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $appEntity = $this->createAppEntity(Uuid::randomHex(), [
            [
                'entries' => [
                    [
                        'cookie' => 'swag-app-something',
                        'snippet_name' => 'first.something',
                    ],
                    [
                        'cookie' => 'swag-app-lorem-ipsum',
                        'snippet_name' => 'second.lorem.ipsum',
                    ],
                ],
                'snippet_name' => CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED,
            ],
        ]);
        $this->createListener($appEntity)->__invoke($event);

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
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $firstAppEntity = $this->createAppEntity(Uuid::randomHex(), [
            [
                'entries' => [
                    [
                        'cookie' => 'swag-app-foobar',
                        'snippet_name' => 'other.app.foobar',
                    ],
                ],
                'snippet_name' => 'app.cookie.group.name',
            ],
        ]);
        $secondAppEntity = $this->createAppEntity(Uuid::randomHex(), [
            [
                'entries' => [
                    [
                        'cookie' => 'swag-app-something',
                        'snippet_name' => 'first.something',
                    ],
                    [
                        'cookie' => 'swag-app-lorem-ipsum',
                        'snippet_name' => 'second.lorem.ipsum',
                    ],
                ],
                'snippet_name' => 'app.cookie.group.name',
            ],
        ]);
        $this->createListener($firstAppEntity, $secondAppEntity)->__invoke($event);

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
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->createListener()->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertEmpty($groups);
    }

    public function testCookieGroupRedirectsViaManifestTargetGroup(): void
    {
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $appEntity = $this->createAppEntity(Uuid::randomHex(), [
            [
                'snippet_name' => 'myapp.tracking',
                'snippet_description' => 'Tracking cookie for marketing',
                'cookie' => 'myapp-tracking',
                'value' => 'tracker-value',
                'expiration' => '90',
                'target_group' => CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING,
            ],
        ]);

        $this->createListener($appEntity)->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertCount(1, $groups);

        // Should be redirected to marketing group via manifest target_group
        $marketingGroup = $groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING);
        static::assertNotNull($marketingGroup);

        $entries = $marketingGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(1, $entries);

        $trackingCookie = $entries->first();
        static::assertNotNull($trackingCookie);
        static::assertSame('myapp-tracking', $trackingCookie->cookie);
        static::assertSame('myapp.tracking', $trackingCookie->name);
        // Verify all optional properties are preserved when redirecting
        static::assertSame('Tracking cookie for marketing', $trackingCookie->description);
        static::assertSame('tracker-value', $trackingCookie->value);
        static::assertSame(90, $trackingCookie->expiration);
    }

    public function testCookieGroupRedirectsViaManifestDefaultTargetGroup(): void
    {
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $appEntity = $this->createAppEntity(Uuid::randomHex(), [
            [
                'snippet_name' => 'myapp.preferences',
                'cookie' => 'myapp-preferences',
                'default_target_group' => CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES,
            ],
        ]);

        $this->createListener($appEntity)->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertCount(1, $groups);

        // Should be redirected to comfort features group via manifest default_target_group
        $comfortGroup = $groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES);
        static::assertNotNull($comfortGroup);

        $entries = $comfortGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(1, $entries);
        static::assertSame('myapp-preferences', $entries->first()?->cookie);
    }

    public function testManifestTargetGroupHasHigherPriorityThanDefaultTargetGroup(): void
    {
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $appEntity = $this->createAppEntity(Uuid::randomHex(), [
            [
                'snippet_name' => 'myapp.priority',
                'cookie' => 'myapp-priority',
                'target_group' => CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL,
                'default_target_group' => CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING,
            ],
        ]);

        $this->createListener($appEntity)->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertCount(1, $groups);

        // target_group should have higher priority than default_target_group
        $statisticalGroup = $groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        static::assertNotNull($statisticalGroup);

        $entries = $statisticalGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(1, $entries);
        static::assertSame('myapp-priority', $entries->first()?->cookie);
    }

    public function testRedirectsCookieGroupWithMultipleEntries(): void
    {
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $appEntity = $this->createAppEntity(Uuid::randomHex(), [
            [
                'entries' => [
                    [
                        'cookie' => 'myapp-analytics-pageview',
                        'snippet_name' => 'myapp.analytics.pageview',
                        'value' => '',
                        'expiration' => '30',
                    ],
                    [
                        'cookie' => 'myapp-analytics-session',
                        'snippet_name' => 'myapp.analytics.session',
                        'expiration' => '0',
                    ],
                ],
                'snippet_name' => 'myapp.analytics.group',
                'snippet_description' => 'Analytics cookies from my app',
                'target_group' => CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL,
            ],
        ]);

        $this->createListener($appEntity)->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertCount(1, $groups);

        // Cookie group should be redirected to statistical group
        $statisticalGroup = $groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        static::assertNotNull($statisticalGroup);

        // All entries from the app cookie group should be added to the statistical group
        $entries = $statisticalGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(2, $entries);

        $pageviewCookie = $entries->get('myapp-analytics-pageview');
        static::assertNotNull($pageviewCookie);
        static::assertSame('myapp-analytics-pageview', $pageviewCookie->cookie);
        static::assertSame('myapp.analytics.pageview', $pageviewCookie->name);
        static::assertSame('', $pageviewCookie->value);
        static::assertSame(30, $pageviewCookie->expiration);

        $sessionCookie = $entries->get('myapp-analytics-session');
        static::assertNotNull($sessionCookie);
        static::assertSame('myapp-analytics-session', $sessionCookie->cookie);
        static::assertSame('myapp.analytics.session', $sessionCookie->name);
        static::assertSame(0, $sessionCookie->expiration);
    }

    /**
     * @param list<Cookie> $cookies
     */
    private function createAppEntity(string $appId, array $cookies): AppEntity
    {
        return (new AppEntity())->assign([
            'id' => $appId,
            '_uniqueIdentifier' => $appId,
            'active' => true,
            'cookies' => $cookies,
        ]);
    }

    private function createListener(?AppEntity $entity = null, ?AppEntity $entity2 = null): AppCookieCollectListener
    {
        $apps = [];

        if ($entity !== null) {
            $apps[] = $entity;
        }

        if ($entity2 !== null) {
            $apps[] = $entity2;
        }

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([
            new AppCollection($apps),
        ]);

        return new AppCookieCollectListener($appRepo);
    }
}
