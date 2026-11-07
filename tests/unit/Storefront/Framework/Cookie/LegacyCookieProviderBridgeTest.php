<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\Triggerer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Framework\Cookie\AppCookieProvider;
use Shopware\Storefront\Framework\Cookie\CookieProvider as LegacyCookieProvider;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Shopware\Storefront\Framework\Cookie\LegacyCookieProviderBridge;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 *
 * @phpstan-import-type CookieGroupArray from CookieProviderInterface
 */
#[Package('discovery')]
#[CoversClass(LegacyCookieProviderBridge::class)]
#[DisabledFeatures(['v6.9.0.0'])]
class LegacyCookieProviderBridgeTest extends TestCase
{
    use EnvTestBehaviour;

    private const SESSION_OPTIONS = ['name' => 'custom-session'];

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

    public function testUndecoratedLegacyChainChangesNothing(): void
    {
        $withoutBridge = $this->collect(null);
        $withBridge = $this->collect(new AppCookieProvider(new LegacyCookieProvider(self::SESSION_OPTIONS)));

        static::assertEquals($withoutBridge, $withBridge);
    }

    public function testNewLegacyGroupCoexistsWithEventListeners(): void
    {
        $decorator = $this->decorate(static function (array $groups): array {
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
        });

        $groups = $this->collect($decorator, static function (CookieGroupCollectEvent $event): void {
            $group = new CookieGroup('event.group');
            $group->setEntries(new CookieEntryCollection([new CookieEntry('event-cookie')]));
            $event->cookieGroupCollection->add($group);
        });

        $legacyGroup = $groups->get('legacy.group');
        static::assertNotNull($legacyGroup);
        static::assertSame('Translated: legacy.group', $legacyGroup->name);
        static::assertSame('Translated: legacy.description', $legacyGroup->description);
        $cookie = $legacyGroup->getEntries()?->get('legacy-cookie');
        static::assertNotNull($cookie);
        static::assertSame('Translated: legacy.entry', $cookie->name);
        static::assertSame('1', $cookie->value);
        static::assertSame(30, $cookie->expiration);
        static::assertFalse($cookie->hidden);
        static::assertNotNull($groups->get('event.group')?->getEntries()?->get('event-cookie'));
        static::assertNotNull($groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED)?->getEntries()?->get('custom-session'));
    }

    public function testEntryAddedToCoreGroupKeepsCoreAndListenerEntries(): void
    {
        $decorator = $this->decorate(static function (array $groups): array {
            foreach ($groups as &$group) {
                if ($group['snippet_name'] === CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES) {
                    $group['entries'][] = ['snippet_name' => 'legacy.chat', 'cookie' => 'legacy-chat', 'value' => '1', 'expiration' => '30'];
                }
            }

            return $groups;
        });

        $groups = $this->collect($decorator, static function (CookieGroupCollectEvent $event): void {
            $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES)?->getEntries()?->add(new CookieEntry('wishlist-enabled'));
        });

        $entries = $groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES)?->getEntries();
        static::assertNotNull($entries);
        static::assertSame(['youtube-video', 'vimeo-video', 'legacy-chat', 'wishlist-enabled'], array_keys($entries->getElements()));
        static::assertSame('Translated: legacy.chat', $entries->get('legacy-chat')?->name);
    }

    public function testEntryAddedToEmptyCoreGroupRestoresTheGroup(): void
    {
        $decorator = $this->decorate(static function (array $groups): array {
            foreach ($groups as &$group) {
                if ($group['snippet_name'] === CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING) {
                    $group['entries'][] = ['snippet_name' => 'legacy.ads', 'cookie' => 'legacy-ads'];
                }
            }

            return $groups;
        });

        $marketing = $this->collect($decorator)->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING);

        static::assertNotNull($marketing);
        static::assertSame('Translated: cookie.groupMarketingDescription', $marketing->description);
        static::assertSame(['legacy-ads'], array_keys($marketing->getEntries()?->getElements() ?? []));
    }

    public function testRemovedAndChangedEntriesAreApplied(): void
    {
        $decorator = $this->decorate(static function (array $groups): array {
            foreach ($groups as &$group) {
                if ($group['snippet_name'] !== CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES) {
                    continue;
                }

                $group['entries'] = array_values(array_filter($group['entries'], static fn (array $entry): bool => $entry['cookie'] !== 'youtube-video'));
                $group['entries'][0]['expiration'] = '60';
            }

            return $groups;
        });

        $entries = $this->collect($decorator)->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES)?->getEntries();

        static::assertNotNull($entries);
        static::assertSame(['vimeo-video'], array_keys($entries->getElements()));
        static::assertSame(60, $entries->get('vimeo-video')?->expiration);
    }

    public function testRemovedGroupAndChangedGroupPropertiesAreApplied(): void
    {
        $decorator = $this->decorate(static function (array $groups): array {
            $groups = array_values(array_filter($groups, static fn (array $group): bool => $group['snippet_name'] !== CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES));
            $groups[0]['snippet_description'] = 'legacy.requiredDescription';

            return $groups;
        });

        $groups = $this->collect($decorator);

        static::assertNull($groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES));
        $required = $groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        static::assertNotNull($required);
        static::assertTrue($required->isRequired);
        static::assertSame('Translated: legacy.requiredDescription', $required->description);
        static::assertCount(4, $required->getEntries() ?? []);
    }

    public function testChangedRequiredFlagValueAndExpirationOfACoreGroupAreApplied(): void
    {
        $decorator = $this->decorate(static function (array $groups): array {
            foreach ($groups as &$group) {
                if ($group['snippet_name'] === CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES) {
                    $group['isRequired'] = true;
                    $group['value'] = 'legacy-value';
                    $group['expiration'] = '7';
                }
            }

            return $groups;
        });

        $comfortFeatures = $this->collect($decorator)->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES);

        static::assertNotNull($comfortFeatures);
        static::assertTrue($comfortFeatures->isRequired);
        static::assertSame('legacy-value', $comfortFeatures->value);
        static::assertSame(7, $comfortFeatures->expiration);
        static::assertSame(['youtube-video', 'vimeo-video'], array_keys($comfortFeatures->getEntries()?->getElements() ?? []));
    }

    public function testLegacyCookieReplacesTheCookieOfAGroupAddedByAListener(): void
    {
        $decorator = $this->decorate(static function (array $groups): array {
            $groups[] = ['snippet_name' => 'shared.group', 'cookie' => 'legacy-cookie'];

            return $groups;
        });

        $groups = $this->collect($decorator, static function (CookieGroupCollectEvent $event): void {
            $group = new CookieGroup('shared.group');
            $group->setCookie('event-cookie');
            $event->cookieGroupCollection->add($group);
        }, listenerPriority: 20000);

        $sharedGroup = $groups->get('shared.group');
        static::assertNotNull($sharedGroup);
        static::assertSame('legacy-cookie', $sharedGroup->getCookie());
        static::assertNull($sharedGroup->getEntries());
    }

    public function testLegacyEntriesAreIgnoredForAGroupWithASingleCookieAddedByAListener(): void
    {
        $decorator = $this->decorate(static function (array $groups): array {
            $groups[] = ['snippet_name' => 'shared.group', 'entries' => [['cookie' => 'legacy-cookie']]];

            return $groups;
        });

        $groups = $this->collect($decorator, static function (CookieGroupCollectEvent $event): void {
            $group = new CookieGroup('shared.group');
            $group->setCookie('event-cookie');
            $event->cookieGroupCollection->add($group);
        }, listenerPriority: 20000);

        $sharedGroup = $groups->get('shared.group');
        static::assertNotNull($sharedGroup);
        static::assertSame('event-cookie', $sharedGroup->getCookie());
        static::assertNull($sharedGroup->getEntries());
    }

    public function testLegacyEntriesAreAddedToAGroupWithoutCookiesAddedByAListener(): void
    {
        $decorator = $this->decorate(static function (array $groups): array {
            $groups[] = ['snippet_name' => 'shared.group', 'entries' => [['cookie' => 'legacy-cookie']]];

            return $groups;
        });

        $groups = $this->collect($decorator, static function (CookieGroupCollectEvent $event): void {
            $event->cookieGroupCollection->add(new CookieGroup('shared.group'));
        }, listenerPriority: 20000);

        static::assertSame(['legacy-cookie'], array_keys($groups->get('shared.group')?->getEntries()?->getElements() ?? []));
    }

    #[DataProvider('coreGroupsTurnedIntoStandaloneCookie')]
    public function testCoreGroupTurnedIntoAStandaloneCookieKeepsTheCookie(string $technicalName): void
    {
        $decorator = $this->decorate(static function (array $groups) use ($technicalName): array {
            foreach ($groups as &$group) {
                if ($group['snippet_name'] === $technicalName) {
                    unset($group['entries']);
                    $group['cookie'] = 'legacy-cookie';
                    $group['value'] = '1';
                }
            }

            return $groups;
        });

        $group = $this->collect($decorator)->get($technicalName);

        static::assertNotNull($group);
        static::assertNull($group->getEntries());
        static::assertSame('legacy-cookie', $group->getCookie());
        static::assertSame('1', $group->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function coreGroupsTurnedIntoStandaloneCookie(): iterable
    {
        yield 'group without entries' => [CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING];
        yield 'group with entries' => [CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES];
    }

    public function testCoreGroupTurnedIntoAStandaloneCookieKeepsEntriesOfListeners(): void
    {
        $decorator = $this->decorate(static function (array $groups): array {
            foreach ($groups as &$group) {
                if ($group['snippet_name'] === CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING) {
                    unset($group['entries']);
                    $group['cookie'] = 'legacy-cookie';
                }
            }

            return $groups;
        });

        $groups = $this->collect($decorator, static function (CookieGroupCollectEvent $event): void {
            $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING)?->getEntries()?->add(new CookieEntry('event-cookie'));
        }, listenerPriority: 20000);

        $marketing = $groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING);
        static::assertNotNull($marketing);
        static::assertNull($marketing->getCookie());
        static::assertSame(['event-cookie'], array_keys($marketing->getEntries()?->getElements() ?? []));
    }

    public function testCoreGroupRemovedByAnEarlierListenerStaysRemoved(): void
    {
        $decorator = $this->decorate(static function (array $groups): array {
            foreach ($groups as &$group) {
                if ($group['snippet_name'] === CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES) {
                    $group['entries'][] = ['cookie' => 'legacy-chat'];
                }
            }

            return $groups;
        });

        $groups = $this->collect($decorator, static function (CookieGroupCollectEvent $event): void {
            $event->cookieGroupCollection->remove(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES);
        }, listenerPriority: 20000);

        static::assertNull($groups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES));
    }

    public function testLegacyCookieConverting(): void
    {
        $decorator = $this->decorate(static function (array $groups): array {
            $groups[] = [
                'snippet_name' => 'test-group-1',
                'cookie' => 'test-cookie',
                'value' => 'test-value',
                'expiration' => '10',
            ];
            $groups[] = [
                'snippet_name' => 'test-group-will-be-ignored',
                'cookie' => null,
            ];
            $groups[] = [
                'snippet_name' => 'test-group-2',
                'entries' => [
                    ['cookie' => 'test-cookie-2', 'snippet_description' => 'test-description'],
                    ['cookie' => 'test-cookie-3', 'value' => 0, 'snippet_name' => null, 'snippet_description' => ''],
                    ['cookie' => 'test-cookie-4', 'value' => true],
                ],
            ];

            return $groups;
        });

        $groups = $this->collect($decorator, translate: false);

        static::assertNull($groups->get('test-group-will-be-ignored'));
        $testGroup1 = $groups->get('test-group-1');
        static::assertNotNull($testGroup1);
        static::assertNull($testGroup1->getEntries());
        static::assertSame('test-cookie', $testGroup1->getCookie());
        static::assertSame('test-value', $testGroup1->value);
        static::assertSame(10, $testGroup1->expiration);

        $entries = $groups->get('test-group-2')?->getEntries();
        static::assertNotNull($entries);
        static::assertCount(3, $entries);
        static::assertSame('test-description', $entries->get('test-cookie-2')?->description);
        $entry3 = $entries->get('test-cookie-3');
        static::assertNotNull($entry3);
        static::assertSame('0', $entry3->value);
        static::assertNull($entry3->name);
        static::assertNull($entry3->description);
        static::assertSame('1', $entries->get('test-cookie-4')?->value);
    }

    public function testMissingSnippetNameInGroupThrowsWithoutDeprecationNotice(): void
    {
        $invalidGroup = ['cookie' => 'test-cookie'];
        $decorator = $this->decorate(static fn (array $groups): array => [$invalidGroup]);

        $this->expectExceptionObject(Feature::silent('v6.9.0.0', static fn () => CookieException::invalidLegacyCookieGroupProvided($invalidGroup)));
        $this->setEnvVars(['TESTS_RUNNING' => false]);
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->never())->method('deprecation');
        Feature::$triggerer = $triggerer;

        $this->collect($decorator);
    }

    public function testSnippetNameInGroupWithWrongTypeThrows(): void
    {
        $invalidGroup = ['cookie' => 'test-cookie', 'snippet_name' => 123];
        $decorator = $this->decorate(static fn (array $groups): array => [$invalidGroup]);

        $this->expectExceptionObject(Feature::silent('v6.9.0.0', static fn () => CookieException::invalidLegacyCookieGroupProvided($invalidGroup)));

        $this->collect($decorator);
    }

    public function testMissingCookieInEntryThrowsWithoutDeprecationNotice(): void
    {
        $invalidEntry = ['snippet_name' => 'test-cookie'];
        $decorator = $this->decorate(static fn (array $groups): array => [['snippet_name' => 'test-group-1', 'entries' => [$invalidEntry]]]);

        $this->expectExceptionObject(Feature::silent('v6.9.0.0', static fn () => CookieException::invalidLegacyCookieEntryProvided($invalidEntry)));
        $this->setEnvVars(['TESTS_RUNNING' => false]);
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->never())->method('deprecation');
        Feature::$triggerer = $triggerer;

        $this->collect($decorator);
    }

    public function testCookieInEntryWithWrongTypeThrows(): void
    {
        $invalidEntry = ['snippet_name' => 'test-cookie', 'cookie' => 123];
        $decorator = $this->decorate(static fn (array $groups): array => [['snippet_name' => 'test-group-1', 'entries' => [$invalidEntry]]]);

        $this->expectExceptionObject(Feature::silent('v6.9.0.0', static fn () => CookieException::invalidLegacyCookieEntryProvided($invalidEntry)));

        $this->collect($decorator);
    }

    /**
     * @param \Closure(list<array<string, mixed>>): list<array<string, mixed>> $modifier
     */
    private function decorate(\Closure $modifier): CookieProviderInterface
    {
        return new LegacyCookieProviderDecoratorForTesting(
            new AppCookieProvider(new LegacyCookieProvider(self::SESSION_OPTIONS)),
            $modifier,
        );
    }

    /**
     * @param (\Closure(CookieGroupCollectEvent): void)|null $listener
     */
    private function collect(
        ?CookieProviderInterface $legacyProvider,
        ?\Closure $listener = null,
        int $listenerPriority = 0,
        bool $translate = true,
    ): CookieGroupCollection {
        $dispatcher = new EventDispatcher();
        if ($legacyProvider !== null) {
            $dispatcher->addListener(CookieGroupCollectEvent::class, new LegacyCookieProviderBridge($legacyProvider, self::SESSION_OPTIONS), 10000);
        }
        if ($listener !== null) {
            $dispatcher->addListener(CookieGroupCollectEvent::class, $listener, $listenerPriority);
        }

        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $translate ? 'Translated: ' . $key : $key);

        return (new CookieProvider($dispatcher, $translator, static::createStub(ScriptExecutor::class), self::SESSION_OPTIONS))
            ->getCookieGroups(new Request(), Generator::generateSalesChannelContext());
    }
}

/**
 * @internal
 */
class LegacyCookieProviderDecoratorForTesting implements CookieProviderInterface
{
    /**
     * @param \Closure(list<array<string, mixed>>): list<array<string, mixed>> $modifier
     */
    public function __construct(
        private readonly CookieProviderInterface $inner,
        private readonly \Closure $modifier,
    ) {
    }

    public function getCookieGroups(): array
    {
        /** @phpstan-ignore return.type (Extensions return loosely typed arrays, the bridge must cope with them) */
        return ($this->modifier)($this->inner->getCookieGroups());
    }
}
