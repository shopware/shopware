<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute\AppSeoUrlRouteEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriterGateway;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlRouteProvider;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlSynchronizer;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlSynchronizer::class)]
class AppSeoUrlSynchronizerTest extends TestCase
{
    private const APP_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const IMPRINT_ROUTE = 'storefront.app.SwagSeoUrlApp.imprint';

    private const TEASER_ROUTE = 'storefront.app.SwagSeoUrlApp.product-teaser';

    private const SALES_CHANNEL_ID = 'cccccccccccccccccccccccccccccccc';

    private const ENGLISH_ID = 'dddddddddddddddddddddddddddddddd';

    private const GERMAN_ID = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    /**
     * @var list<array{context: Context, routeName: string, foreignKeys: list<string>, seoUrls: list<array<string, mixed>>, salesChannelId: string}>
     */
    private array $written = [];

    /**
     * @var list<array{routeName: string, ids: list<string>}>
     */
    private array $regenerated = [];

    private SeoUrlPersister $persister;

    private SeoUrlUpdater $seoUrlUpdater;

    protected function setUp(): void
    {
        $this->written = [];
        $this->regenerated = [];

        $persister = static::createStub(SeoUrlPersister::class);
        $persister->method('forceUpdateSeoUrls')->willReturnCallback(
            function (Context $context, string $routeName, array $foreignKeys, iterable $seoUrls, SalesChannelEntity $salesChannel): void {
                /** @var list<string> $keys */
                $keys = array_values($foreignKeys);
                /** @var list<array<string, mixed>> $rows */
                $rows = [...$seoUrls];

                $this->written[] = [
                    'context' => $context,
                    'routeName' => $routeName,
                    'foreignKeys' => $keys,
                    'seoUrls' => $rows,
                    'salesChannelId' => $salesChannel->getId(),
                ];
            }
        );
        $this->persister = $persister;

        $seoUrlUpdater = static::createStub(SeoUrlUpdater::class);
        $seoUrlUpdater->method('update')->willReturnCallback(function (string $routeName, array $ids): void {
            /** @var list<string> $ids */
            $this->regenerated[] = ['routeName' => $routeName, 'ids' => $ids];
        });
        $this->seoUrlUpdater = $seoUrlUpdater;
    }

    public function testEveryDomainLanguageGetsItsOwnCanonicalRow(): void
    {
        $synchronizer = $this->synchronizer(
            $this->provider(static: [$this->staticRoute(['en-GB' => 'imprint', 'de-DE' => 'impressum'])]),
            $this->salesChannelRepository($this->salesChannel([self::ENGLISH_ID => 'en-GB', self::GERMAN_ID => 'de-DE']))
        );

        $synchronizer->syncStaticRoutes();

        static::assertCount(2, $this->written);

        static::assertSame(self::IMPRINT_ROUTE, $this->written[0]['routeName']);
        static::assertSame(self::SALES_CHANNEL_ID, $this->written[0]['salesChannelId']);
        static::assertSame(self::ENGLISH_ID, $this->written[0]['context']->getLanguageId());
        static::assertSame([Uuid::fromStringToHex(self::IMPRINT_ROUTE)], $this->written[0]['foreignKeys']);
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

    public function testAnUndeclaredLocaleFallsBackToTheDefaultLocalePath(): void
    {
        $synchronizer = $this->synchronizer(
            $this->provider(static: [$this->staticRoute(['en-GB' => 'imprint', 'de-DE' => 'impressum'])]),
            $this->salesChannelRepository($this->salesChannel([self::GERMAN_ID => 'fr-FR']))
        );

        $synchronizer->syncStaticRoutes();

        static::assertCount(1, $this->written);
        static::assertSame('imprint', $this->written[0]['seoUrls'][0]['seoPathInfo']);
    }

    public function testWithoutADefaultLocalePathTheFirstDeclaredPathIsUsed(): void
    {
        $synchronizer = $this->synchronizer(
            $this->provider(static: [$this->staticRoute(['de-DE' => 'impressum'])]),
            $this->salesChannelRepository($this->salesChannel([self::GERMAN_ID => 'fr-FR']))
        );

        $synchronizer->syncStaticRoutes();

        static::assertCount(1, $this->written);
        static::assertSame('impressum', $this->written[0]['seoUrls'][0]['seoPathInfo']);
    }

    public function testDomainsSharingALanguageAreWrittenOnce(): void
    {
        $salesChannel = $this->salesChannel([self::ENGLISH_ID => 'en-GB']);
        $second = new SalesChannelDomainEntity();
        $second->setUniqueIdentifier(Uuid::randomHex());
        $second->setLanguageId(self::ENGLISH_ID);
        $domains = $salesChannel->getDomains();
        static::assertInstanceOf(SalesChannelDomainCollection::class, $domains);
        $domains->add($second);

        $synchronizer = $this->synchronizer(
            $this->provider(static: [$this->staticRoute(['en-GB' => 'imprint'])]),
            $this->salesChannelRepository($salesChannel)
        );

        $synchronizer->syncStaticRoutes();

        static::assertCount(1, $this->written);
    }

    public function testOnlyActiveStorefrontSalesChannelsAreSynchronised(): void
    {
        $criteria = null;

        $salesChannelRepository = StaticEntityRepository::of(
            SalesChannelCollection::class,
            [function (Criteria $given) use (&$criteria): SalesChannelCollection {
                $criteria = $given;

                return new SalesChannelCollection([$this->salesChannel([self::ENGLISH_ID => 'en-GB'])]);
            }]
        );

        $this->synchronizer(
            $this->provider(static: [$this->staticRoute(['en-GB' => 'imprint'])]),
            $salesChannelRepository
        )->syncStaticRoutes();

        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertEquals(
            [
                new EqualsFilter('active', true),
                new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_API)]),
            ],
            $criteria->getFilters()
        );
        static::assertTrue($criteria->hasAssociation('domains'));
        static::assertTrue($criteria->getAssociation('domains')->hasAssociation('language'));
        static::assertTrue($criteria->getAssociation('domains.language')->hasAssociation('locale'));
    }

    public function testWithoutStaticRoutesTheSalesChannelsAreNotLoaded(): void
    {
        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class, [
            static fn (): SalesChannelCollection => static::fail('sales channels must not be loaded without static routes'),
        ]);

        $this->synchronizer($this->provider(), $salesChannelRepository)->syncStaticRoutes();

        static::assertSame([], $this->written);
    }

    public function testTheAppFilterIsPassedThroughToTheProviderForStaticRoutes(): void
    {
        $provider = $this->createMock(AppSeoUrlRouteProvider::class);
        $provider->expects($this->once())
            ->method('getStaticRoutes')
            ->with(self::APP_ID)
            ->willReturn(new EntityCollection());

        $this->synchronizer($provider, StaticEntityRepository::of(SalesChannelCollection::class, []))
            ->syncStaticRoutes(self::APP_ID);
    }

    public function testEveryEntityRouteIsRegeneratedInChunks(): void
    {
        $limits = [];

        $productRepository = StaticEntityRepository::of(
            ProductCollection::class,
            [
                static function (Criteria $criteria) use (&$limits): array {
                    $limits[] = $criteria->getLimit();

                    return ['product-1', 'product-2'];
                },
                static function (Criteria $criteria) use (&$limits): array {
                    $limits[] = $criteria->getLimit();

                    return ['product-3'];
                },
                static fn (): array => [],
            ],
            $this->productDefinition()
        );

        $this->synchronizer(
            $this->provider(entity: [$this->entityRoute('product')]),
            StaticEntityRepository::of(SalesChannelCollection::class, []),
            $productRepository
        )->regenerateEntityRoutes();

        static::assertSame([
            ['routeName' => self::TEASER_ROUTE, 'ids' => ['product-1', 'product-2']],
            ['routeName' => self::TEASER_ROUTE, 'ids' => ['product-3']],
        ], $this->regenerated);

        static::assertSame([500, 500], $limits);
    }

    public function testEntityRoutesBoundToAnUnknownEntityAreSkipped(): void
    {
        $this->synchronizer(
            $this->provider(entity: [$this->entityRoute('ce_blog')]),
            StaticEntityRepository::of(SalesChannelCollection::class, [])
        )->regenerateEntityRoutes();

        static::assertSame([], $this->regenerated);
    }

    public function testTheAppFilterIsPassedThroughToTheProviderForEntityRoutes(): void
    {
        $provider = $this->createMock(AppSeoUrlRouteProvider::class);
        $provider->expects($this->once())
            ->method('getEntityRoutes')
            ->with(self::APP_ID)
            ->willReturn(new EntityCollection());

        $this->synchronizer($provider, StaticEntityRepository::of(SalesChannelCollection::class, []))
            ->regenerateEntityRoutes(self::APP_ID);
    }

    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param StaticEntityRepository<ProductCollection>|null $productRepository
     */
    private function synchronizer(
        AppSeoUrlRouteProvider $provider,
        EntityRepository $salesChannelRepository,
        ?StaticEntityRepository $productRepository = null
    ): AppSeoUrlSynchronizer {
        $container = new Container();
        $container->set('product.repository', $productRepository ?? StaticEntityRepository::of(ProductCollection::class, []));

        return new AppSeoUrlSynchronizer(
            $provider,
            $salesChannelRepository,
            $this->persister,
            $this->seoUrlUpdater,
            new DefinitionInstanceRegistry(
                $container,
                ['product' => ProductDefinition::class],
                ['product' => 'product.repository']
            )
        );
    }

    /**
     * @param list<AppSeoUrlRouteEntity> $static
     * @param list<AppSeoUrlRouteEntity> $entity
     */
    private function provider(array $static = [], array $entity = []): AppSeoUrlRouteProvider
    {
        $provider = static::createStub(AppSeoUrlRouteProvider::class);
        $provider->method('getStaticRoutes')->willReturn(new EntityCollection($static));
        $provider->method('getEntityRoutes')->willReturn(new EntityCollection($entity));

        return $provider;
    }

    /**
     * @return StaticEntityRepository<SalesChannelCollection>
     */
    private function salesChannelRepository(SalesChannelEntity ...$salesChannels): StaticEntityRepository
    {
        return new StaticEntityRepository([new SalesChannelCollection($salesChannels)]);
    }

    /**
     * @param non-empty-array<string, string> $paths
     */
    private function staticRoute(array $paths): AppSeoUrlRouteEntity
    {
        $route = $this->route('imprint');
        $route->paths = $paths;

        return $route;
    }

    private function entityRoute(string $entityName): AppSeoUrlRouteEntity
    {
        $route = $this->route('product-teaser');
        $route->entityName = $entityName;
        $route->defaultTemplate = '{{ ' . $entityName . '.translated.name }}';

        return $route;
    }

    private function route(string $name): AppSeoUrlRouteEntity
    {
        $route = new AppSeoUrlRouteEntity();
        $route->id = Uuid::randomHex();
        $route->appId = self::APP_ID;
        $route->name = $name;
        $route->routeName = 'storefront.app.SwagSeoUrlApp.' . $name;
        $route->hook = $name;
        $route->setUniqueIdentifier($route->id);

        return $route;
    }

    /**
     * @param array<string, string> $localesByLanguage
     */
    private function salesChannel(array $localesByLanguage): SalesChannelEntity
    {
        $domains = new SalesChannelDomainCollection();

        foreach ($localesByLanguage as $languageId => $localeCode) {
            $locale = new LocaleEntity();
            $locale->setUniqueIdentifier(Uuid::randomHex());
            $locale->setCode($localeCode);

            $language = new LanguageEntity();
            $language->setUniqueIdentifier($languageId);
            $language->setLocale($locale);

            $domain = new SalesChannelDomainEntity();
            $domain->setUniqueIdentifier(Uuid::randomHex());
            $domain->setLanguageId($languageId);
            $domain->setLanguage($language);

            $domains->add($domain);
        }

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setUniqueIdentifier(self::SALES_CHANNEL_ID);
        $salesChannel->setId(self::SALES_CHANNEL_ID);
        $salesChannel->setDomains($domains);

        return $salesChannel;
    }

    private function productDefinition(): ProductDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class],
            Validation::createValidator(),
            new StaticEntityWriterGateway()
        );

        $definition = $registry->getByEntityName(ProductDefinition::ENTITY_NAME);
        static::assertInstanceOf(ProductDefinition::class, $definition);

        return $definition;
    }
}
