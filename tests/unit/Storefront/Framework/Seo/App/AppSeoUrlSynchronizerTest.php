<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlConfig;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlSynchronizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlSynchronizer::class)]
class AppSeoUrlSynchronizerTest extends TestCase
{
    private const APP_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const OTHER_APP_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const IMPRINT_ROUTE = 'storefront.app.SwagSeoUrlApp.imprint';

    private const CONTACT_ROUTE = 'storefront.app.SwagSeoUrlApp.contact';

    private const SALES_CHANNEL_ID = 'cccccccccccccccccccccccccccccccc';

    private const OTHER_SALES_CHANNEL_ID = 'dddddddddddddddddddddddddddddddd';

    private const SALES_CHANNEL_WITHOUT_DOMAINS_ID = '33333333333333333333333333333333';

    private const GERMAN_ID = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    private const SWISS_GERMAN_ID = 'ffffffffffffffffffffffffffffffff';

    private const AMERICAN_ID = '11111111111111111111111111111111';

    private const FRENCH_ID = '22222222222222222222222222222222';

    private const LOCALES = [
        Defaults::LANGUAGE_SYSTEM => 'en-GB',
        self::GERMAN_ID => 'de-DE',
        self::SWISS_GERMAN_ID => 'de-CH',
        self::AMERICAN_ID => 'en-US',
        self::FRENCH_ID => 'fr-FR',
    ];

    /**
     * @var list<array{context: Context, routeName: string, foreignKeys: list<string>, seoUrls: list<array<string, mixed>>, salesChannel: SalesChannelEntity}>
     */
    private array $written = [];

    private SeoUrlPersister $seoUrlPersister;

    private LanguageLocaleCodeProvider $languageLocaleProvider;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->written = [];
        $this->requestStack = new RequestStack();

        $seoUrlPersister = static::createStub(SeoUrlPersister::class);
        $seoUrlPersister->method('forceUpdateSeoUrls')->willReturnCallback(
            function (Context $context, string $routeName, array $foreignKeys, iterable $seoUrls, SalesChannelEntity $salesChannel): void {
                $this->written[] = [
                    'context' => $context,
                    'routeName' => $routeName,
                    'foreignKeys' => array_values($foreignKeys),
                    'seoUrls' => iterator_to_array($seoUrls, false),
                    'salesChannel' => $salesChannel,
                ];
            }
        );
        $this->seoUrlPersister = $seoUrlPersister;

        $languageLocaleProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $languageLocaleProvider->method('getLocaleForLanguageId')->willReturnCallback(
            static fn (string $languageId): string => self::LOCALES[$languageId] ?? static::fail('Unknown language ' . $languageId)
        );
        $this->languageLocaleProvider = $languageLocaleProvider;
    }

    public function testEveryDomainLanguageGetsACanonicalModifiedSeoUrl(): void
    {
        $salesChannel = $this->salesChannel(self::SALES_CHANNEL_ID, $this->domain(Defaults::LANGUAGE_SYSTEM), $this->domain(self::GERMAN_ID));

        $this->synchronizer(
            [$this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint', 'de-DE' => 'impressum']))],
            $this->salesChannelRepository($salesChannel)
        )->syncStaticRoutes();

        static::assertCount(2, $this->written);

        static::assertSame(self::IMPRINT_ROUTE, $this->written[0]['routeName']);
        static::assertSame([Uuid::fromStringToHex(self::IMPRINT_ROUTE)], $this->written[0]['foreignKeys']);
        static::assertSame($salesChannel, $this->written[0]['salesChannel']);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $this->written[0]['context']->getLanguageId());
        static::assertSame([[
            'foreignKey' => Uuid::fromStringToHex(self::IMPRINT_ROUTE),
            'pathInfo' => '/storefront/script/imprint',
            'seoPathInfo' => 'imprint',
            'salesChannelId' => self::SALES_CHANNEL_ID,
            'isCanonical' => true,
            'isModified' => true,
            'isDeleted' => false,
        ]], $this->written[0]['seoUrls']);

        static::assertSame(self::GERMAN_ID, $this->written[1]['context']->getLanguageId());
        static::assertSame('impressum', $this->written[1]['seoUrls'][0]['seoPathInfo']);
    }

    /**
     * @param list<string> $expectedLanguageChain
     */
    #[DataProvider('domainLanguages')]
    public function testTheSeoUrlIsWrittenWithTheLanguageChainOfTheDomain(string $languageId, ?string $parentLanguageId, array $expectedLanguageChain): void
    {
        $this->synchronizer(
            [$this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint']))],
            $this->salesChannelRepository($this->salesChannel(self::SALES_CHANNEL_ID, $this->domain($languageId, $parentLanguageId)))
        )->syncStaticRoutes();

        static::assertCount(1, $this->written);
        static::assertSame($expectedLanguageChain, $this->written[0]['context']->getLanguageIdChain());
    }

    /**
     * @return iterable<string, array{string, ?string, list<string>}>
     */
    public static function domainLanguages(): iterable
    {
        yield 'a root language falls back to the system language' => [
            self::GERMAN_ID,
            null,
            [self::GERMAN_ID, Defaults::LANGUAGE_SYSTEM],
        ];
        yield 'a child language falls back to its parent before the system language' => [
            self::SWISS_GERMAN_ID,
            self::GERMAN_ID,
            [self::SWISS_GERMAN_ID, self::GERMAN_ID, Defaults::LANGUAGE_SYSTEM],
        ];
        yield 'the system language is not repeated' => [
            Defaults::LANGUAGE_SYSTEM,
            null,
            [Defaults::LANGUAGE_SYSTEM],
        ];
        yield 'the system language as parent is not repeated' => [
            self::AMERICAN_ID,
            Defaults::LANGUAGE_SYSTEM,
            [self::AMERICAN_ID, Defaults::LANGUAGE_SYSTEM],
        ];
    }

    /**
     * @param array<string, string> $paths
     */
    #[DataProvider('pathsByLocale')]
    public function testThePathOfTheFirstLocaleInTheLanguageChainIsUsed(string $languageId, ?string $parentLanguageId, array $paths, string $expectedPath): void
    {
        $this->synchronizer(
            [$this->feature($this->seoUrl('imprint', $paths))],
            $this->salesChannelRepository($this->salesChannel(self::SALES_CHANNEL_ID, $this->domain($languageId, $parentLanguageId)))
        )->syncStaticRoutes();

        static::assertCount(1, $this->written);
        static::assertSame($expectedPath, $this->written[0]['seoUrls'][0]['seoPathInfo']);
    }

    /**
     * @return iterable<string, array{string, ?string, array<string, string>, string}>
     */
    public static function pathsByLocale(): iterable
    {
        yield 'the path of the domain language wins' => [
            self::GERMAN_ID,
            null,
            ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
            'impressum',
        ];
        yield 'a child language without a path of its own uses the path of its parent' => [
            self::SWISS_GERMAN_ID,
            self::GERMAN_ID,
            ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
            'impressum',
        ];
        yield 'the order of the language chain beats the order of the declaration' => [
            self::SWISS_GERMAN_ID,
            self::GERMAN_ID,
            ['de-DE' => 'impressum', 'de-CH' => 'impressum-schweiz'],
            'impressum-schweiz',
        ];
        yield 'a language without a path uses the path of the system language' => [
            self::FRENCH_ID,
            null,
            ['de-DE' => 'impressum', 'en-GB' => 'imprint'],
            'imprint',
        ];
        yield 'without a path for any language of the chain the first declared path is used' => [
            self::FRENCH_ID,
            null,
            ['de-DE' => 'impressum', 'nl-NL' => 'colofon'],
            'impressum',
        ];
    }

    public function testDomainsSharingALanguageAreWrittenOnce(): void
    {
        $this->synchronizer(
            [$this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint']))],
            $this->salesChannelRepository($this->salesChannel(self::SALES_CHANNEL_ID, $this->domain(self::GERMAN_ID), $this->domain(self::GERMAN_ID)))
        )->syncStaticRoutes();

        static::assertCount(1, $this->written);
    }

    public function testEveryStaticSeoUrlIsWrittenForEverySalesChannel(): void
    {
        $this->synchronizer(
            [
                $this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint'])),
                $this->feature($this->seoUrl('contact', ['en-GB' => 'contact'])),
            ],
            $this->salesChannelRepository(
                $this->salesChannel(self::SALES_CHANNEL_ID, $this->domain(Defaults::LANGUAGE_SYSTEM)),
                $this->salesChannel(self::OTHER_SALES_CHANNEL_ID, $this->domain(Defaults::LANGUAGE_SYSTEM)),
            )
        )->syncStaticRoutes();

        static::assertSame(
            [
                [self::SALES_CHANNEL_ID, self::IMPRINT_ROUTE, 'imprint'],
                [self::SALES_CHANNEL_ID, self::CONTACT_ROUTE, 'contact'],
                [self::OTHER_SALES_CHANNEL_ID, self::IMPRINT_ROUTE, 'imprint'],
                [self::OTHER_SALES_CHANNEL_ID, self::CONTACT_ROUTE, 'contact'],
            ],
            array_map(
                static fn (array $write): array => [$write['salesChannel']->getId(), $write['routeName'], $write['seoUrls'][0]['seoPathInfo']],
                $this->written
            )
        );
    }

    public function testSalesChannelsWithoutDomainsGetNoSeoUrls(): void
    {
        $withoutLoadedDomains = new SalesChannelEntity();
        $withoutLoadedDomains->setId(self::OTHER_SALES_CHANNEL_ID);

        $this->synchronizer(
            [$this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint']))],
            $this->salesChannelRepository(
                $withoutLoadedDomains,
                $this->salesChannel(self::SALES_CHANNEL_WITHOUT_DOMAINS_ID),
                $this->salesChannel(self::SALES_CHANNEL_ID, $this->domain(Defaults::LANGUAGE_SYSTEM)),
            )
        )->syncStaticRoutes();

        static::assertCount(1, $this->written);
        static::assertSame(self::SALES_CHANNEL_ID, $this->written[0]['salesChannel']->getId());
    }

    /**
     * @param list<string> $expectedRouteNames
     */
    #[DataProvider('appFilters')]
    public function testTheSyncCanBeLimitedToOneApp(?string $appId, array $expectedRouteNames): void
    {
        $this->synchronizer(
            [
                $this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint']), appId: self::APP_ID),
                $this->feature($this->seoUrl('contact', ['en-GB' => 'contact']), appId: self::OTHER_APP_ID),
            ],
            $this->salesChannelRepository($this->salesChannel(self::SALES_CHANNEL_ID, $this->domain(Defaults::LANGUAGE_SYSTEM)))
        )->syncStaticRoutes($appId);

        static::assertSame($expectedRouteNames, array_column($this->written, 'routeName'));
    }

    /**
     * @return iterable<string, array{?string, list<string>}>
     */
    public static function appFilters(): iterable
    {
        yield 'without an app the static SEO URLs of all active apps are written' => [null, [self::IMPRINT_ROUTE, self::CONTACT_ROUTE]];
        yield 'with an app only its static SEO URLs are written' => [self::OTHER_APP_ID, [self::CONTACT_ROUTE]];
    }

    public function testWithoutStaticSeoUrlsOfActiveAppsNoSalesChannelIsLoaded(): void
    {
        $this->synchronizer([], $this->salesChannelRepositoryThatMustNotBeSearched())->syncStaticRoutes();

        static::assertSame([], $this->written);
    }

    public function testWithoutStaticSeoUrlsOfTheGivenAppNoSalesChannelIsLoaded(): void
    {
        $this->synchronizer(
            [$this->feature($this->seoUrl('contact', ['en-GB' => 'contact']), appId: self::OTHER_APP_ID)],
            $this->salesChannelRepositoryThatMustNotBeSearched()
        )->syncStaticRoutes(self::APP_ID);

        static::assertSame([], $this->written);
    }

    public function testOnlyActiveSalesChannelsThatAreNotHeadlessAreLoadedWithTheirDomainLanguages(): void
    {
        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class, [
            static function (Criteria $criteria): SalesChannelCollection {
                static::assertEquals(
                    [
                        new EqualsFilter('active', true),
                        new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_API)]),
                    ],
                    $criteria->getFilters()
                );
                static::assertTrue($criteria->hasAssociation('domains'));
                static::assertTrue($criteria->getAssociation('domains')->hasAssociation('language'));

                return new SalesChannelCollection();
            },
        ]);

        $this->synchronizer([$this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint']))], $salesChannelRepository)->syncStaticRoutes();

        static::assertSame([], $salesChannelRepository->searches);
    }

    public function testThePathInfoPointsToTheScriptEndpointOfTheHook(): void
    {
        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(static function (string $name, array $parameters): string {
            static::assertSame('frontend.script_endpoint', $name);
            static::assertSame(['hook' => 'legal-notice'], $parameters);

            return '/storefront/script/legal-notice';
        });

        $this->synchronizer(
            [$this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint'], hook: 'legal-notice'))],
            $this->salesChannelRepository($this->salesChannel(self::SALES_CHANNEL_ID, $this->domain(Defaults::LANGUAGE_SYSTEM))),
            $router
        )->syncStaticRoutes();

        static::assertCount(1, $this->written);
        static::assertSame('/storefront/script/legal-notice', $this->written[0]['seoUrls'][0]['pathInfo']);
    }

    #[DataProvider('basePaths')]
    public function testTheBasePathOfTheMainRequestIsNotPartOfThePathInfo(?string $basePath, string $generatedPath, string $expectedPathInfo): void
    {
        if ($basePath !== null) {
            $this->requestStack->push(Request::create(
                $basePath . '/admin',
                server: ['SCRIPT_FILENAME' => '/var/www/html' . $basePath . '/index.php', 'SCRIPT_NAME' => $basePath . '/index.php']
            ));
        }

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn($generatedPath);

        $this->synchronizer(
            [$this->feature($this->seoUrl('imprint', ['en-GB' => 'imprint']))],
            $this->salesChannelRepository($this->salesChannel(self::SALES_CHANNEL_ID, $this->domain(Defaults::LANGUAGE_SYSTEM))),
            $router
        )->syncStaticRoutes();

        static::assertCount(1, $this->written);
        static::assertSame($expectedPathInfo, $this->written[0]['seoUrls'][0]['pathInfo']);
    }

    /**
     * @return iterable<string, array{?string, string, string}>
     */
    public static function basePaths(): iterable
    {
        yield 'without a main request the generated path is used' => [null, '/storefront/script/imprint', '/storefront/script/imprint'];
        yield 'the base path of the main request is stripped' => ['/shop', '/shop/storefront/script/imprint', '/storefront/script/imprint'];
        yield 'a generated path outside of the base path is used as it is' => ['/shop', '/storefront/script/imprint', '/storefront/script/imprint'];
    }

    /**
     * @param list<AppFeature<AppSeoUrlConfig>> $features
     * @param StaticEntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    private function synchronizer(array $features, StaticEntityRepository $salesChannelRepository, ?RouterInterface $router = null): AppSeoUrlSynchronizer
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturnCallback(static function (string $featureClass) use ($features): array {
            static::assertSame(AppSeoUrlConfig::class, $featureClass);

            return $features;
        });

        if ($router === null) {
            $router = static::createStub(RouterInterface::class);
            $router->method('generate')->willReturnCallback(
                static fn (string $name, array $parameters): string => '/storefront/script/' . $parameters['hook']
            );
        }

        return new AppSeoUrlSynchronizer(
            $storage,
            $salesChannelRepository,
            $this->seoUrlPersister,
            $this->languageLocaleProvider,
            $router,
            $this->requestStack,
        );
    }

    /**
     * @return StaticEntityRepository<SalesChannelCollection>
     */
    private function salesChannelRepository(SalesChannelEntity ...$salesChannels): StaticEntityRepository
    {
        return new StaticEntityRepository([new SalesChannelCollection($salesChannels)]);
    }

    /**
     * @return StaticEntityRepository<SalesChannelCollection>
     */
    private function salesChannelRepositoryThatMustNotBeSearched(): StaticEntityRepository
    {
        return StaticEntityRepository::of(SalesChannelCollection::class, [
            static fn (): SalesChannelCollection => static::fail('No sales channel must be loaded without static SEO URLs to write'),
        ]);
    }

    /**
     * @return AppFeature<AppSeoUrlConfig>
     */
    private function feature(AppSeoUrlConfig $seoUrl, string $appId = self::APP_ID): AppFeature
    {
        return new AppFeature(
            appId: $appId,
            appName: 'SwagSeoUrlApp',
            appActive: true,
            appVersion: '1.0.0',
            appHasSecret: false,
            createdAt: new \DateTimeImmutable('2026-01-01 00:00:00'),
            config: $seoUrl,
        );
    }

    /**
     * @param array<string, string> $paths
     */
    private function seoUrl(string $name, array $paths, ?string $hook = null): AppSeoUrlConfig
    {
        return new AppSeoUrlConfig(
            name: $name,
            routeName: 'storefront.app.SwagSeoUrlApp.' . $name,
            hook: $hook ?? $name,
            paths: $paths,
        );
    }

    private function salesChannel(string $id, SalesChannelDomainEntity ...$domains): SalesChannelEntity
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($id);
        $salesChannel->setDomains(new SalesChannelDomainCollection($domains));

        return $salesChannel;
    }

    private function domain(string $languageId, ?string $parentLanguageId = null): SalesChannelDomainEntity
    {
        $language = new LanguageEntity();
        $language->setId($languageId);
        $language->setParentId($parentLanguageId);

        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setLanguageId($languageId);
        $domain->setLanguage($language);

        return $domain;
    }
}
