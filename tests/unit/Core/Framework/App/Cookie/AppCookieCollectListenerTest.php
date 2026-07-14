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
use Shopware\Core\Framework\App\Cookie\AppCookieCollectListener;
use Shopware\Core\Framework\App\Cookie\CookieConfig;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AppCookieCollectListener::class)]
class AppCookieCollectListenerTest extends TestCase
{
    public function testSingleCookie(): void
    {
        $event = $this->event();

        $this->listener(
            new CookieConfig('swag.analytics.name', null, 'swag-analytics', '', 30, [])
        )->__invoke($event);

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
        $event = $this->event();

        $this->listener(
            new CookieConfig('app.cookies.group', 'app.cookies.group.description', null, null, null, [
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
            ])
        )->__invoke($event);

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

        $this->listener(
            new CookieConfig(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED, null, null, null, null, [
                ['cookie' => 'swag-app-something', 'snippet_name' => 'first.something'],
                ['cookie' => 'swag-app-lorem-ipsum', 'snippet_name' => 'second.lorem.ipsum'],
            ])
        )->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertCount(1, $groups);

        $firstGroup = $groups->first();
        static::assertNotNull($firstGroup);
        static::assertSame(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED, $firstGroup->name);
        $entries = $firstGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(3, $entries);

        static::assertNotNull($entries->get('core.something'));
        static::assertNotNull($entries->get('swag-app-something'));
        static::assertNotNull($entries->get('swag-app-lorem-ipsum'));
    }

    public function testMergeCookiesFromMultipleApps(): void
    {
        $event = $this->event();

        $this->listener(
            new CookieConfig('app.cookie.group.name', null, null, null, null, [
                ['cookie' => 'swag-app-foobar', 'snippet_name' => 'other.app.foobar'],
            ]),
            new CookieConfig('app.cookie.group.name', null, null, null, null, [
                ['cookie' => 'swag-app-something', 'snippet_name' => 'first.something'],
                ['cookie' => 'swag-app-lorem-ipsum', 'snippet_name' => 'second.lorem.ipsum'],
            ])
        )->__invoke($event);

        $groups = $event->cookieGroupCollection;
        static::assertCount(1, $groups);

        $firstGroup = $groups->first();
        static::assertNotNull($firstGroup);
        static::assertSame('app.cookie.group.name', $firstGroup->name);
        $entries = $firstGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(3, $entries);

        static::assertNotNull($entries->get('swag-app-something'));
        static::assertNotNull($entries->get('swag-app-lorem-ipsum'));
        static::assertNotNull($entries->get('swag-app-foobar'));
    }

    public function testItIgnoresDeactivatedApps(): void
    {
        $event = $this->event();

        // forActiveApps returns nothing when no active app declares cookies
        $this->listener()->__invoke($event);

        static::assertEmpty($event->cookieGroupCollection);
    }

    private function event(): CookieGroupCollectEvent
    {
        return new CookieGroupCollectEvent(
            new CookieGroupCollection(),
            new Request(),
            Generator::generateSalesChannelContext()
        );
    }

    private function listener(CookieConfig ...$configs): AppCookieCollectListener
    {
        $features = array_map(
            static fn (CookieConfig $config): AppFeature => new AppFeature(
                'app-id',
                'app-name',
                true,
                '1.0.0',
                true,
                new \DateTimeImmutable('2024-01-01'),
                $config,
            ),
            $configs,
        );

        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn($features);

        return new AppCookieCollectListener($storage);
    }
}
