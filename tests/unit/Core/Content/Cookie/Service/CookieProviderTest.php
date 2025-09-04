<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Storefront\Framework\Cookie\CookieProvider as LegacyCookieProvider;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(CookieProvider::class)]
class CookieProviderTest extends TestCase
{
    public function testGetCookieGroups(): void
    {
        $eventDispatcher = new CollectingEventDispatcher();
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $cookieGroups = (new CookieProvider(
            $eventDispatcher,
            $translator,
            ['name' => 'test-session-name-']
        )
        )->getCookieGroups(Generator::generateSalesChannelContext());

        $events = $eventDispatcher->getEvents();
        static::assertCount(1, $events);
        $collectEvent = $events[0];
        static::assertInstanceOf(CookieGroupCollectEvent::class, $collectEvent);
        static::assertSame($cookieGroups, $collectEvent->cookieGroupCollection);

        static::assertCount(2, $cookieGroups);

        $requiredGroup = $cookieGroups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        static::assertInstanceOf(CookieGroup::class, $requiredGroup);
        static::assertNotNull($requiredGroup->getEntries());
        static::assertCount(3, $requiredGroup->getEntries());

        $sessionCookie = $requiredGroup->getEntries()->get('test-session-name-');
        static::assertNotNull($sessionCookie);

        $cookiePreferenceCookie = $requiredGroup->getEntries()->get('cookie-preference');
        static::assertNotNull($cookiePreferenceCookie);
        static::assertTrue($cookiePreferenceCookie->hidden);

        $comfortFeaturesGroup = $cookieGroups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES);
        static::assertInstanceOf(CookieGroup::class, $comfortFeaturesGroup);
        static::assertNotNull($comfortFeaturesGroup->getEntries());
        static::assertCount(1, $comfortFeaturesGroup->getEntries());
    }

    public function testGetCookieGroupsWithTranslation(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(CookieGroupCollectEvent::class, static function (CookieGroupCollectEvent $event): void {
            $cookieGroupEntry = new CookieEntry('test-cookie');
            $cookieGroupEntry->snippetKeyName = 'cookie.entry.test';
            $cookieGroupEntry->snippetKeyDescription = 'cookie.entry.test.description';

            $newGroup = new CookieGroup('cookie.group.test');
            $newGroup->snippetKeyDescription = 'cookie.group.test.description';
            $newGroup->setEntries(new CookieEntryCollection([$cookieGroupEntry]));
            $event->cookieGroupCollection->add($newGroup);
        });

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn ($key) => 'Translated: ' . $key);
        $cookieGroups = (new CookieProvider(
            $eventDispatcher,
            $translator,
            ['name' => 'test-session-name-']
        )
        )->getCookieGroups(Generator::generateSalesChannelContext(), true);

        static::assertCount(3, $cookieGroups);
        $group = $cookieGroups->get('cookie.group.test');
        static::assertInstanceOf(CookieGroup::class, $group);
        static::assertSame('cookie.group.test', $group->snippetKeyName);
        static::assertSame('Translated: cookie.group.test', $group->translatedName);
        static::assertSame('cookie.group.test.description', $group->snippetKeyDescription);
        static::assertSame('Translated: cookie.group.test.description', $group->translatedDescription);
        $entries = $group->getEntries();
        static::assertNotNull($entries);
        static::assertCount(1, $entries);
        $entry = $entries->get('test-cookie');
        static::assertNotNull($entry);
        static::assertSame('cookie.entry.test', $entry->snippetKeyName);
        static::assertSame('Translated: cookie.entry.test', $entry->translatedName);
        static::assertSame('cookie.entry.test.description', $entry->snippetKeyDescription);
        static::assertSame('Translated: cookie.entry.test.description', $entry->translatedDescription);
        static::assertSame('test-cookie', $entry->cookie);
    }

    public function testNewCookieAddedViaEvent(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(CookieGroupCollectEvent::class, static function (CookieGroupCollectEvent $event): void {
            $newGroup = new CookieGroup('test');
            $newGroup->setCookie('test-cookie');
            $event->cookieGroupCollection->add($newGroup);
        });

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $cookieGroups = (new CookieProvider(
            $eventDispatcher,
            $translator,
            ['name' => 'test-session-name-']
        )
        )->getCookieGroups(Generator::generateSalesChannelContext());
        static::assertCount(3, $cookieGroups);

        $testGroup = $cookieGroups->get('test');
        static::assertInstanceOf(CookieGroup::class, $testGroup);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyCookieConverting(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $legacyCookieProvider = new LegacyCookieProvider(['name' => 'test-session-name-']);

        $cookieGroups = (new CookieProvider(
            new EventDispatcher(),
            $translator,
            [],
            $legacyCookieProvider,
        ))->getCookieGroups(Generator::generateSalesChannelContext());

        static::assertCount(2, $cookieGroups);

        $requiredGroup = $cookieGroups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        static::assertInstanceOf(CookieGroup::class, $requiredGroup);
        static::assertTrue($requiredGroup->isRequired);
        static::assertNotNull($requiredGroup->getEntries());
        static::assertCount(3, $requiredGroup->getEntries());

        $sessionCookie = $requiredGroup->getEntries()->get('test-session-name-');
        static::assertNotNull($sessionCookie);

        $cookiePreferenceCookie = $requiredGroup->getEntries()->get('cookie-preference');
        static::assertNotNull($cookiePreferenceCookie);
        static::assertTrue($cookiePreferenceCookie->hidden);
    }
}
