<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
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
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlRouteLoader;
use Shopware\Storefront\Framework\Seo\App\AppStaticSeoUrlSynchronizer;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppStaticSeoUrlSynchronizer::class)]
class AppStaticSeoUrlSynchronizerTest extends TestCase
{
    private const APP_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const ROUTE_NAME = 'storefront.app.SwagSeoUrlApp.imprint';

    private const SALES_CHANNEL_ID = 'cccccccccccccccccccccccccccccccc';

    private const ENGLISH_ID = 'dddddddddddddddddddddddddddddddd';

    private const GERMAN_ID = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    /**
     * @var list<array{context: Context, routeName: string, foreignKeys: list<string>, seoUrls: list<array<string, mixed>>, salesChannelId: string}>
     */
    private array $written = [];

    private SeoUrlPersister $persister;

    protected function setUp(): void
    {
        $this->written = [];

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
    }

    public function testEveryDomainLanguageGetsItsOwnCanonicalRow(): void
    {
        $synchronizer = $this->createSynchronizer(
            [$this->staticRoute(['en-GB' => 'imprint', 'de-DE' => 'impressum'])],
            [$this->salesChannel([self::ENGLISH_ID => 'en-GB', self::GERMAN_ID => 'de-DE'])]
        );

        $synchronizer->sync();

        static::assertCount(2, $this->written);

        static::assertSame(self::ROUTE_NAME, $this->written[0]['routeName']);
        static::assertSame(self::SALES_CHANNEL_ID, $this->written[0]['salesChannelId']);
        static::assertSame(self::ENGLISH_ID, $this->written[0]['context']->getLanguageId());
        static::assertSame([Uuid::fromStringToHex(self::ROUTE_NAME)], $this->written[0]['foreignKeys']);
        static::assertSame([[
            'foreignKey' => Uuid::fromStringToHex(self::ROUTE_NAME),
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
        $synchronizer = $this->createSynchronizer(
            [$this->staticRoute(['en-GB' => 'imprint', 'de-DE' => 'impressum'])],
            [$this->salesChannel([self::GERMAN_ID => 'fr-FR'])]
        );

        $synchronizer->sync();

        static::assertCount(1, $this->written);
        static::assertSame('imprint', $this->written[0]['seoUrls'][0]['seoPathInfo']);
    }

    public function testWithoutADefaultLocalePathTheFirstDeclaredPathIsUsed(): void
    {
        $synchronizer = $this->createSynchronizer(
            [$this->staticRoute(['de-DE' => 'impressum'])],
            [$this->salesChannel([self::GERMAN_ID => 'fr-FR'])]
        );

        $synchronizer->sync();

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

        $synchronizer = $this->createSynchronizer(
            [$this->staticRoute(['en-GB' => 'imprint'])],
            [$salesChannel]
        );

        $synchronizer->sync();

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

        $synchronizer = new AppStaticSeoUrlSynchronizer(
            $this->routeLoader([$this->staticRoute(['en-GB' => 'imprint'])]),
            $salesChannelRepository,
            $this->persister
        );

        $synchronizer->sync();

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

    public function testTheAppFilterIsPassedThroughToTheRouteLoader(): void
    {
        $loader = $this->createMock(AppSeoUrlRouteLoader::class);
        $loader->expects($this->once())
            ->method('getStaticRoutes')
            ->with(self::APP_ID)
            ->willReturn([]);

        $synchronizer = new AppStaticSeoUrlSynchronizer(
            $loader,
            StaticEntityRepository::of(SalesChannelCollection::class, []),
            $this->persister
        );

        $synchronizer->sync(self::APP_ID);
    }

    public function testWithoutStaticRoutesNothingIsLoadedOrWritten(): void
    {
        $persister = $this->createMock(SeoUrlPersister::class);
        $persister->expects($this->never())->method('forceUpdateSeoUrls');

        $synchronizer = new AppStaticSeoUrlSynchronizer(
            $this->routeLoader([]),
            StaticEntityRepository::of(SalesChannelCollection::class, [
                static fn (): SalesChannelCollection => static::fail('sales channels must not be loaded without static routes'),
            ]),
            $persister
        );

        $synchronizer->sync();
    }

    /**
     * @param list<array{appId: string, routeName: string, hook: string, paths: non-empty-array<string, string>}> $routes
     * @param list<SalesChannelEntity> $salesChannels
     */
    private function createSynchronizer(array $routes, array $salesChannels): AppStaticSeoUrlSynchronizer
    {
        return new AppStaticSeoUrlSynchronizer(
            $this->routeLoader($routes),
            StaticEntityRepository::of(SalesChannelCollection::class, [new SalesChannelCollection($salesChannels)]),
            $this->persister
        );
    }

    /**
     * @param list<array{appId: string, routeName: string, hook: string, paths: non-empty-array<string, string>}> $routes
     */
    private function routeLoader(array $routes): AppSeoUrlRouteLoader
    {
        $loader = static::createStub(AppSeoUrlRouteLoader::class);
        $loader->method('getStaticRoutes')->willReturn($routes);

        return $loader;
    }

    /**
     * @param non-empty-array<string, string> $paths
     *
     * @return array{appId: string, routeName: string, hook: string, paths: non-empty-array<string, string>}
     */
    private function staticRoute(array $paths): array
    {
        return [
            'appId' => self::APP_ID,
            'routeName' => self::ROUTE_NAME,
            'hook' => 'imprint',
            'paths' => $paths,
        ];
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
}
