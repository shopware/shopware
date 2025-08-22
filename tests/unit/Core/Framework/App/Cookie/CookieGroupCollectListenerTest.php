<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Cookie\CookieGroupCollectListener;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 *
 * @phpstan-import-type Cookie from AppEntity
 */
#[CoversClass(CookieGroupCollectListener::class)]
class CookieGroupCollectListenerTest extends TestCase
{
    public function testSingleCookie(): void
    {
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
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
        static::assertSame('swag.analytics.name', $firstGroup->snippetKeyName);
        static::assertSame('swag-analytics', $firstGroup->getCookie());
        static::assertSame('', $firstGroup->value);
        static::assertSame(30, $firstGroup->expiration);
    }

    public function testCookieGroup(): void
    {
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            Generator::generateSalesChannelContext()
        );

        $appEntity = $this->createAppEntity(Uuid::randomHex(), [
            [
                'entries' => [
                    [
                        'cookie' => 'swag-app-something',
                        'snippet_name' => 'first.cookie',
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
        static::assertSame('app.cookies.group', $firstGroup->snippetKeyName);
        static::assertSame('app.cookies.group.description', $firstGroup->snippetKeyDescription);

        $entries = $firstGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(2, $entries);

        $firstCookie = $entries->get('swag-app-something');
        static::assertNotNull($firstCookie);
        static::assertSame('swag-app-something', $firstCookie->cookie);
        static::assertSame('first.cookie', $firstCookie->snippetKeyName);

        $secondCookie = $entries->get('swag-app-lorem-ipsum');
        static::assertNotNull($secondCookie);
        static::assertSame('swag-app-lorem-ipsum', $secondCookie->cookie);
        static::assertSame('second.cookie', $secondCookie->snippetKeyName);
    }

    public function testMergeCookiesWithCoreGroup(): void
    {
        $coreCookieEntry = new CookieEntry('core.something');
        $coreCookieEntry->snippetKeyName = 'cookie.core';

        $coreCookieGroup = new CookieGroup('cookie.groupRequired');
        $coreCookieGroup->setEntries(new CookieEntryCollection([$coreCookieEntry]));

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([$coreCookieGroup]),
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
                'snippet_name' => 'cookie.groupRequired',
            ],
        ]);
        $this->createListener($appEntity)->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertCount(1, $groups);

        $firstGroup = $groups->first();
        static::assertNotNull($firstGroup);
        static::assertSame('cookie.groupRequired', $firstGroup->snippetKeyName);
        $entries = $firstGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(3, $entries);

        $coreCookieEntry = $entries->get('core.something');
        static::assertNotNull($coreCookieEntry);
        static::assertSame('core.something', $coreCookieEntry->cookie);
        static::assertSame('cookie.core', $coreCookieEntry->snippetKeyName);

        $firstCookie = $entries->get('swag-app-something');
        static::assertNotNull($firstCookie);
        static::assertSame('swag-app-something', $firstCookie->cookie);
        static::assertSame('first.something', $firstCookie->snippetKeyName);

        $secondCookie = $entries->get('swag-app-lorem-ipsum');
        static::assertNotNull($secondCookie);
        static::assertSame('swag-app-lorem-ipsum', $secondCookie->cookie);
        static::assertSame('second.lorem.ipsum', $secondCookie->snippetKeyName);
    }

    public function testMergeCookiesFromMultipleApps(): void
    {
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
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
        static::assertSame('app.cookie.group.name', $firstGroup->snippetKeyName);
        $entries = $firstGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(3, $entries);

        $firstAppFirstCookie = $entries->get('swag-app-something');
        static::assertNotNull($firstAppFirstCookie);
        static::assertSame('swag-app-something', $firstAppFirstCookie->cookie);
        static::assertSame('first.something', $firstAppFirstCookie->snippetKeyName);

        $firstAppSecondCookie = $entries->get('swag-app-lorem-ipsum');
        static::assertNotNull($firstAppSecondCookie);
        static::assertSame('swag-app-lorem-ipsum', $firstAppSecondCookie->cookie);
        static::assertSame('second.lorem.ipsum', $firstAppSecondCookie->snippetKeyName);

        $secondAppCookie = $entries->get('swag-app-foobar');
        static::assertNotNull($secondAppCookie);
        static::assertSame('swag-app-foobar', $secondAppCookie->cookie);
        static::assertSame('other.app.foobar', $secondAppCookie->snippetKeyName);
    }

    public function testItIgnoresDeactivatedApps(): void
    {
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            Generator::generateSalesChannelContext()
        );

        $this->createListener()->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertEmpty($groups);
    }

    /**
     * @param list<Cookie> $cookies
     */
    private function createAppEntity(string $appId, array $cookies): AppEntity
    {
        return (new AppEntity())->assign([
            'id' => $appId,
            '_uniqueIdentifier' => $appId,
            'cookies' => $cookies,
        ]);
    }

    private function createListener(AppEntity ...$appEntity): CookieGroupCollectListener
    {
        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([
            new AppCollection([...$appEntity]),
        ]);

        return new CookieGroupCollectListener($appRepo);
    }
}
