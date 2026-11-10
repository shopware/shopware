<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Hook\CookieGroupCollectHook;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\Triggerer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Storefront\Framework\Cookie\AppCookieProvider;
use Shopware\Storefront\Framework\Cookie\CookieProvider as LegacyCookieProvider;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CookieProvider::class)]
#[DisabledFeatures(['v6.9.0.0'])]
class CookieProviderTest extends TestCase
{
    use EnvTestBehaviour;

    private ?Triggerer $triggererBackup;

    protected function setUp(): void
    {
        $this->triggererBackup = Feature::$triggerer;
    }

    protected function tearDown(): void
    {
        Feature::$triggerer = $this->triggererBackup;
        parent::tearDown();
    }

    public function testLegacyDecoratorAndNewEventCoexistWithTheMajorFlagActive(): void
    {
        static::assertTrue(Feature::isActive('v6.8.0.0'));
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CookieGroupCollectEvent::class, static function (CookieGroupCollectEvent $event): void {
            $group = new CookieGroup('event.group');
            $group->setEntries(new CookieEntryCollection([new CookieEntry('event-cookie')]));
            $event->cookieGroupCollection->add($group);
        });
        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => 'Translated: ' . $key);
        $legacy = new CompatibilityCookieDecorator(new AppCookieProvider(new LegacyCookieProvider(['name' => 'custom-session'])));
        $provider = new CookieProvider($dispatcher, $translator, static::createStub(ScriptExecutor::class), ['name' => 'custom-session'], $legacy);

        $groups = $provider->getCookieGroups(new Request(), Generator::generateSalesChannelContext());

        $legacyGroup = $groups->get('legacy.group');
        static::assertNotNull($legacyGroup);
        static::assertSame('Translated: legacy.group', $legacyGroup->name);
        static::assertSame('Translated: legacy.description', $legacyGroup->description);
        $entries = $legacyGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(1, $entries);
        $cookie = $entries->get('legacy-cookie');
        static::assertNotNull($cookie);
        static::assertSame('Translated: legacy.entry', $cookie->name);
        static::assertSame('1', $cookie->value);
        static::assertSame(30, $cookie->expiration);
        static::assertFalse($cookie->hidden);
        $eventGroup = $groups->get('event.group');
        static::assertNotNull($eventGroup);
        static::assertNotNull($eventGroup->getEntries()?->get('event-cookie'));
        $required = $groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        static::assertNotNull($required);
        static::assertNotNull($required->getEntries()?->get('custom-session'));
    }

    /**
     * @deprecated tag:v6.9.0 - Remove with the legacy provider conversion.
     */
    public function testLegacyProviderIsNotCalledWithThe69FlagActive(): void
    {
        Feature::registerFeature('v6.9.0.0', ['default' => false]);
        $this->setEnvVars(['V6_9_0_0' => '1']);
        $legacy = $this->createMock(CookieProviderInterface::class);
        $legacy->expects($this->never())->method('getCookieGroups');
        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $provider = new CookieProvider(new EventDispatcher(), $translator, static::createStub(ScriptExecutor::class), ['name' => 'modern-session'], $legacy);

        $groups = $provider->getCookieGroups(new Request(), Generator::generateSalesChannelContext());

        static::assertTrue(Feature::isActive('v6.9.0.0'));
        $required = $groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        static::assertNotNull($required);
        static::assertNotNull($required->getEntries()?->get('modern-session'));
    }

    public function testGetCookieGroups(): void
    {
        $eventDispatcher = new CollectingEventDispatcher();
        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $executedHooks = [];
        $scriptExecutor = $this->createMock(ScriptExecutor::class);
        $scriptExecutor->expects($this->once())
            ->method('execute')
            ->willReturnCallback(static function (Hook $hook) use (&$executedHooks): void {
                $executedHooks[] = $hook;
            });

        $cookieGroups = (new CookieProvider(
            $eventDispatcher,
            $translator,
            $scriptExecutor,
            ['name' => 'test-session-name-']
        )
        )->getCookieGroups(new Request(), Generator::generateSalesChannelContext());

        $events = $eventDispatcher->getEvents();
        static::assertCount(1, $events);
        $collectEvent = $events[0];
        static::assertInstanceOf(CookieGroupCollectEvent::class, $collectEvent);
        static::assertSame($cookieGroups, $collectEvent->cookieGroupCollection);

        static::assertCount(1, $executedHooks);
        $hook = $executedHooks[0];
        static::assertInstanceOf(CookieGroupCollectHook::class, $hook);
        static::assertSame($cookieGroups, $hook->getCookieGroups());

        static::assertCount(2, $cookieGroups);

        $requiredGroup = $cookieGroups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        static::assertInstanceOf(CookieGroup::class, $requiredGroup);
        static::assertNotNull($requiredGroup->getEntries());
        static::assertCount(4, $requiredGroup->getEntries());

        $sessionCookie = $requiredGroup->getEntries()->get('test-session-name-');
        static::assertNotNull($sessionCookie);

        $cookiePreferenceCookie = $requiredGroup->getEntries()->get('cookie-preference');
        static::assertNotNull($cookiePreferenceCookie);
        static::assertTrue($cookiePreferenceCookie->hidden);

        $comfortFeaturesGroup = $cookieGroups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES);
        static::assertInstanceOf(CookieGroup::class, $comfortFeaturesGroup);
        static::assertNotNull($comfortFeaturesGroup->getEntries());
        static::assertCount(2, $comfortFeaturesGroup->getEntries());

        $youtubeCookie = $comfortFeaturesGroup->getEntries()->get('youtube-video');
        static::assertNotNull($youtubeCookie);

        $vimeoCookie = $comfortFeaturesGroup->getEntries()->get('vimeo-video');
        static::assertNotNull($vimeoCookie);
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

        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn ($key) => 'Translated: ' . $key);
        $cookieGroups = (new CookieProvider(
            $eventDispatcher,
            $translator,
            static::createStub(ScriptExecutor::class),
            ['name' => 'test-session-name-']
        )
        )->getCookieGroups(new Request(), Generator::generateSalesChannelContext());

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

        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $cookieGroups = (new CookieProvider(
            $eventDispatcher,
            $translator,
            static::createStub(ScriptExecutor::class),
            ['name' => 'test-session-name-']
        )
        )->getCookieGroups(new Request(), Generator::generateSalesChannelContext());
        static::assertCount(3, $cookieGroups);

        $testGroup = $cookieGroups->get('test');
        static::assertInstanceOf(CookieGroup::class, $testGroup);
    }

    public function testLegacyCookieConverting(): void
    {
        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $legacyCookieProvider = new LegacyCookieProviderForTesting(['name' => 'test-session-name-']);

        $cookieGroups = (new CookieProvider(
            new EventDispatcher(),
            $translator,
            static::createStub(ScriptExecutor::class),
            [],
            $legacyCookieProvider,
        ))->getCookieGroups(new Request(), Generator::generateSalesChannelContext());

        static::assertCount(4, $cookieGroups);

        $requiredGroup = $cookieGroups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        static::assertInstanceOf(CookieGroup::class, $requiredGroup);
        static::assertTrue($requiredGroup->isRequired);
        static::assertNotNull($requiredGroup->getEntries());
        static::assertCount(4, $requiredGroup->getEntries());

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

        static::assertCount(3, $testGroup2->getEntries());

        $testGroup2Entry1 = $testGroup2->getEntries()->get('test-cookie-2');
        static::assertNotNull($testGroup2Entry1);
        static::assertSame('test-description', $testGroup2Entry1->description);

        $testGroup2Entry2 = $testGroup2->getEntries()->get('test-cookie-3');
        static::assertNotNull($testGroup2Entry2);
        static::assertSame('0', $testGroup2Entry2->value);
        static::assertNull($testGroup2Entry2->name);
        static::assertNull($testGroup2Entry2->description);

        $testGroup2Entry3 = $testGroup2->getEntries()->get('test-cookie-4');
        static::assertNotNull($testGroup2Entry3);
        static::assertSame('1', $testGroup2Entry3->value);
    }

    public function testLegacyCookieConvertingMissingSnippetNameInGroup(): void
    {
        $invalidGroup = [
            'cookie' => 'test-cookie',
        ];

        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $legacyCookieProvider = new LegacyCookieProviderForTesting(['name' => 'test-session-name-']);
        $legacyCookieProvider->setTestCookieGroups([$invalidGroup]);

        $this->expectExceptionObject(Feature::silent('v6.9.0.0', static fn () => CookieException::invalidLegacyCookieGroupProvided($invalidGroup)));
        $this->setEnvVars(['TESTS_RUNNING' => false]);
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->never())->method('deprecation');
        Feature::$triggerer = $triggerer;
        (new CookieProvider(
            new EventDispatcher(),
            $translator,
            static::createStub(ScriptExecutor::class),
            [],
            $legacyCookieProvider,
        ))->getCookieGroups(new Request(), Generator::generateSalesChannelContext());
    }

    public function testLegacyCookieConvertingSnippetNameInGroupWithWrongType(): void
    {
        $invalidGroup = [
            'cookie' => 'test-cookie',
            'snippet_name' => 123,
        ];

        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $legacyCookieProvider = new LegacyCookieProviderForTesting(['name' => 'test-session-name-']);
        /** @phpstan-ignore argument.type (Intentionally test not allowed values on purpose) */
        $legacyCookieProvider->setTestCookieGroups([$invalidGroup]);

        $this->expectExceptionObject(Feature::silent('v6.9.0.0', static fn () => CookieException::invalidLegacyCookieGroupProvided($invalidGroup)));
        (new CookieProvider(
            new EventDispatcher(),
            $translator,
            static::createStub(ScriptExecutor::class),
            [],
            $legacyCookieProvider,
        ))->getCookieGroups(new Request(), Generator::generateSalesChannelContext());
    }

    public function testLegacyCookieConvertingMissingCookieInEntry(): void
    {
        $invalidEntry = [
            'snippet_name' => 'test-cookie',
        ];

        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $legacyCookieProvider = new LegacyCookieProviderForTesting(['name' => 'test-session-name-']);
        $legacyCookieProvider->setTestCookieGroups([
            [
                'snippet_name' => 'test-group-1',
                'entries' => [$invalidEntry],
            ],
        ]);

        $this->expectExceptionObject(Feature::silent('v6.9.0.0', static fn () => CookieException::invalidLegacyCookieEntryProvided($invalidEntry)));
        $this->setEnvVars(['TESTS_RUNNING' => false]);
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->never())->method('deprecation');
        Feature::$triggerer = $triggerer;
        (new CookieProvider(
            new EventDispatcher(),
            $translator,
            static::createStub(ScriptExecutor::class),
            [],
            $legacyCookieProvider,
        ))->getCookieGroups(new Request(), Generator::generateSalesChannelContext());
    }

    public function testLegacyCookieConvertingCookieInEntryWithWrongType(): void
    {
        $invalidEntry = [
            'snippet_name' => 'test-cookie',
            'cookie' => 123,
        ];

        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $legacyCookieProvider = new LegacyCookieProviderForTesting(['name' => 'test-session-name-']);
        /** @phpstan-ignore argument.type (Intentionally test not allowed values on purpose) */
        $legacyCookieProvider->setTestCookieGroups([
            [
                'snippet_name' => 'test-group-1',
                'entries' => [$invalidEntry],
            ],
        ]);

        $this->expectExceptionObject(Feature::silent('v6.9.0.0', static fn () => CookieException::invalidLegacyCookieEntryProvided($invalidEntry)));
        (new CookieProvider(
            new EventDispatcher(),
            $translator,
            static::createStub(ScriptExecutor::class),
            [],
            $legacyCookieProvider,
        ))->getCookieGroups(new Request(), Generator::generateSalesChannelContext());
    }
}

/**
 * @internal
 *
 * @phpstan-import-type CookieGroupArray from CookieProviderInterface
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
            'snippet_name' => 'test-group-will-be-ignored',
            'cookie' => null,
        ];
        $cookieGroups[] = [
            'snippet_name' => 'test-group-2',
            'entries' => [
                [
                    'cookie' => 'test-cookie-2',
                    'snippet_description' => 'test-description',
                ],
                [
                    'cookie' => 'test-cookie-3',
                    'value' => 0,
                    'snippet_name' => null,
                    'snippet_description' => '',
                ],
                [
                    'cookie' => 'test-cookie-4',
                    'value' => true,
                ],
            ],
        ];

        /** @phpstan-ignore return.type (Intentionally test not allowed values for legacy reasons) */
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

/**
 * @internal
 *
 * @phpstan-import-type CookieGroupArray from CookieProviderInterface
 */
class CompatibilityCookieDecorator implements CookieProviderInterface
{
    public function __construct(private readonly CookieProviderInterface $inner)
    {
    }

    public function getCookieGroups(): array
    {
        $groups = $this->inner->getCookieGroups();
        $groups[] = [
            'snippet_name' => 'legacy.group',
            'snippet_description' => 'legacy.description',
            'entries' => [[
                'cookie' => 'legacy-cookie',
                'snippet_name' => 'legacy.entry',
                'value' => '1',
                'expiration' => '30',
                'hidden' => false,
            ]],
        ];

        return $groups;
    }
}
