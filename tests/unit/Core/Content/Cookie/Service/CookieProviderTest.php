<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Storefront\Framework\Cookie\CookieProvider as LegacyCookieProvider;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
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
            $cookieGroupEntry->name = 'cookie.entry.test';
            $cookieGroupEntry->description = 'cookie.entry.test.description';

            $newGroup = new CookieGroup('cookie.group.test');
            $newGroup->description = 'cookie.group.test.description';
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
        )->getCookieGroups(Generator::generateSalesChannelContext());

        static::assertCount(3, $cookieGroups);
        $group = $cookieGroups->get('cookie.group.test');
        static::assertInstanceOf(CookieGroup::class, $group);
        static::assertSame('Translated: cookie.group.test', $group->name);
        static::assertSame('Translated: cookie.group.test.description', $group->description);
        $entries = $group->getEntries();
        static::assertNotNull($entries);
        static::assertCount(1, $entries);
        $entry = $entries->get('test-cookie');
        static::assertNotNull($entry);
        static::assertSame('Translated: cookie.entry.test', $entry->name);
        static::assertSame('Translated: cookie.entry.test.description', $entry->description);
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
        $legacyCookieProvider = new LegacyCookieProviderForTesting(['name' => 'test-session-name-']);

        $cookieGroups = (new CookieProvider(
            new EventDispatcher(),
            $translator,
            [],
            $legacyCookieProvider,
        ))->getCookieGroups(Generator::generateSalesChannelContext());

        static::assertCount(4, $cookieGroups);

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

        $testGroup1 = $cookieGroups->get('test-group-1');
        static::assertInstanceOf(CookieGroup::class, $testGroup1);
        static::assertNull($testGroup1->getEntries());
        static::assertSame('test-cookie', $testGroup1->getCookie());
        static::assertSame('test-value', $testGroup1->value);
        static::assertSame(10, $testGroup1->expiration);

        $testGroup2 = $cookieGroups->get('test-group-2');
        static::assertInstanceOf(CookieGroup::class, $testGroup2);
        static::assertNotNull($testGroup2->getEntries());
        static::assertCount(1, $testGroup2->getEntries());
        $testGroup2Entry = $testGroup2->getEntries()->get('test-cookie-2');
        static::assertNotNull($testGroup2Entry);
        static::assertSame('test-description', $testGroup2Entry->description);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyCookieConvertingMissingSnippetNameInGroup(): void
    {
        $invalidGroup = [
            'cookie' => 'test-cookie',
        ];

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $legacyCookieProvider = new LegacyCookieProviderForTesting(['name' => 'test-session-name-']);
        /** @phpstan-ignore argument.type (Left out required array key for testing purpose) */
        $legacyCookieProvider->setTestCookieGroups([$invalidGroup]);

        $this->expectExceptionObject(CookieException::invalidLegacyCookieGroupProvided($invalidGroup));
        (new CookieProvider(
            new EventDispatcher(),
            $translator,
            [],
            $legacyCookieProvider,
        ))->getCookieGroups(Generator::generateSalesChannelContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyCookieConvertingMissingCookieInEntry(): void
    {
        $invalidEntry = [
            'snippet_name' => 'test-cookie',
        ];

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $legacyCookieProvider = new LegacyCookieProviderForTesting(['name' => 'test-session-name-']);
        /** @phpstan-ignore argument.type (Left out required array key for testing purpose) */
        $legacyCookieProvider->setTestCookieGroups([
            [
                'snippet_name' => 'test-group-1',
                'entries' => [$invalidEntry],
            ],
        ]);

        $this->expectExceptionObject(CookieException::invalidLegacyCookieEntryProvided($invalidEntry));
        (new CookieProvider(
            new EventDispatcher(),
            $translator,
            [],
            $legacyCookieProvider,
        ))->getCookieGroups(Generator::generateSalesChannelContext());
    }

    public function testGenerateCookieConfigurationHash(): void
    {
        $eventDispatcher = new EventDispatcher();
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $cookieProvider = new CookieProvider(
            $eventDispatcher,
            $translator,
            ['name' => 'test-session-name-']
        );

        $salesChannelContext = Generator::generateSalesChannelContext();
        $cookieGroups = $cookieProvider->getCookieGroups($salesChannelContext);
        $hash = $cookieProvider->generateCookieConfigurationHash($cookieGroups);

        static::assertIsString($hash);
        static::assertNotEmpty($hash);

        // Hash should be consistent for same configuration
        $hash2 = $cookieProvider->generateCookieConfigurationHash($cookieGroups);
        static::assertSame($hash, $hash2);
    }

    public function testGenerateCookieConfigurationHashWithAdditionalCookies(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(CookieGroupCollectEvent::class, static function (CookieGroupCollectEvent $event): void {
            $newGroup = new CookieGroup('test-group');
            $newGroup->setCookie('test-cookie');
            $newGroup->value = 'test-value';
            $newGroup->expiration = 30;
            $event->cookieGroupCollection->add($newGroup);
        });

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $cookieProvider = new CookieProvider(
            $eventDispatcher,
            $translator,
            ['name' => 'test-session-name-']
        );

        $salesChannelContext = Generator::generateSalesChannelContext();
        $cookieGroupsWithExtra = $cookieProvider->getCookieGroups($salesChannelContext);
        $hashWithAdditionalCookie = $cookieProvider->generateCookieConfigurationHash($cookieGroupsWithExtra);

        // Create provider without additional cookies
        $cookieProviderWithoutExtra = new CookieProvider(
            new EventDispatcher(),
            $translator,
            ['name' => 'test-session-name-']
        );

        $cookieGroupsWithoutExtra = $cookieProviderWithoutExtra->getCookieGroups($salesChannelContext);
        $hashWithoutAdditionalCookie = $cookieProviderWithoutExtra->generateCookieConfigurationHash($cookieGroupsWithoutExtra);

        // Hashes should be different when cookie configuration changes
        static::assertNotSame($hashWithAdditionalCookie, $hashWithoutAdditionalCookie);
    }

    public function testGenerateCookieConfigurationHashIncludesTranslations(): void
    {
        $eventDispatcher = new EventDispatcher();

        // Create two providers with different translators
        $translator1 = $this->createMock(TranslatorInterface::class);
        $translator1->method('trans')->willReturnCallback(fn ($key) => 'English: ' . $key);

        $translator2 = $this->createMock(TranslatorInterface::class);
        $translator2->method('trans')->willReturnCallback(fn ($key) => 'Deutsch: ' . $key);

        $cookieProvider1 = new CookieProvider(
            $eventDispatcher,
            $translator1,
            ['name' => 'test-session-name-']
        );

        $cookieProvider2 = new CookieProvider(
            $eventDispatcher,
            $translator2,
            ['name' => 'test-session-name-']
        );

        $salesChannelContext = Generator::generateSalesChannelContext();
        $cookieGroups1 = $cookieProvider1->getCookieGroups($salesChannelContext);
        $cookieGroups2 = $cookieProvider2->getCookieGroups($salesChannelContext);

        $hash1 = $cookieProvider1->generateCookieConfigurationHash($cookieGroups1);
        $hash2 = $cookieProvider2->generateCookieConfigurationHash($cookieGroups2);

        // Hashes should be different when translations change because users need to review updated content
        static::assertNotSame($hash1, $hash2);
    }

    public function testGenerateCookieConfigurationHashIsConsistentRegardlessOfOrder(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        // Create two groups that will be added in different orders
        $group1 = new CookieGroup('test.group.a');
        $group1->description = 'Group A';
        $group1->setEntries(new CookieEntryCollection([
            new CookieEntry('cookie-z'),
            new CookieEntry('cookie-a'),
        ]));

        $group2 = new CookieGroup('test.group.b');
        $group2->description = 'Group B';
        $group2->setEntries(new CookieEntryCollection([
            new CookieEntry('cookie-y'),
            new CookieEntry('cookie-b'),
        ]));

        // Collection 1: A, B order
        $collection1 = new CookieGroupCollection([$group1, $group2]);

        // Collection 2: B, A order (different insertion order)
        $collection2 = new CookieGroupCollection([$group2, $group1]);

        $cookieProvider = new CookieProvider(
            new EventDispatcher(),
            $translator,
            ['name' => 'test-session-name-']
        );

        $hash1 = $cookieProvider->generateCookieConfigurationHash($collection1);
        $hash2 = $cookieProvider->generateCookieConfigurationHash($collection2);

        // Hash should be the same regardless of collection order thanks to sorting
        static::assertSame($hash1, $hash2, 'Hash should be the same regardless of collection order');
    }
}

/**
 * @internal
 *
 * @phpstan-import-type CookieGroupArray from CookieProviderInterface
 * Can be removed with tag:v6.8.0
 */
class LegacyCookieProviderForTesting extends LegacyCookieProvider
{
    /**
     * @var list<CookieGroupArray>|null
     */
    private ?array $testCookieGroups = null;

    public function getCookieGroups(): array
    {
        if ($this->testCookieGroups !== null) {
            return $this->testCookieGroups;
        }

        $cookieGroups = parent::getCookieGroups();
        $cookieGroups[] = [
            'snippet_name' => 'test-group-1',
            'cookie' => 'test-cookie',
            'value' => 'test-value',
            'expiration' => '10',
        ];
        $cookieGroups[] = [
            'snippet_name' => 'test-group-2',
            'entries' => [
                [
                    'cookie' => 'test-cookie-2',
                    'snippet_description' => 'test-description',
                ],
            ],
        ];

        return $cookieGroups;
    }

    /**
     * @param list<CookieGroupArray> $testCookieGroups
     */
    public function setTestCookieGroups(array $testCookieGroups): void
    {
        $this->testCookieGroups = $testCookieGroups;
    }
}
