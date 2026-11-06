<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlUpdater::class)]
class SeoUrlUpdaterTest extends TestCase
{
    private SeoUrlRouteRegistry $seoUrlRouteRegistry;

    private SeoUrlGenerator&MockObject $seoUrlGenerator;

    private SeoUrlPersister&MockObject $seoUrlPersister;

    private Connection&Stub $connection;

    protected function setUp(): void
    {
        $this->seoUrlGenerator = $this->createMock(SeoUrlGenerator::class);
        $this->seoUrlPersister = $this->createMock(SeoUrlPersister::class);
        $this->connection = static::createStub(Connection::class);
    }

    public function testUpdateWithoutDomain(): void
    {
        $seoUrlUpdater = $this->createSeoUrlUpdater([], [], [new ProductPageSeoUrlRoute(new ProductDefinition())]);

        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->seoUrlGenerator->expects($this->never())->method('generate');
        $this->seoUrlPersister->expects($this->never())->method('updateSeoUrls');

        $seoUrlUpdater->update(ProductPageSeoUrlRoute::ROUTE_NAME, []);
    }

    public function testUpdateWithoutDefaultTemplates(): void
    {
        $seoUrlUpdater = $this->createSeoUrlUpdater([], [], [new ProductPageSeoUrlRoute(new ProductDefinition())]);

        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'salesChannelId' => Uuid::randomHex(),
                'languageId' => Uuid::randomHex(),
            ],
        ]);
        $this->connection->method('fetchAllKeyValue')->willReturn([]);

        $this->seoUrlGenerator->expects($this->never())->method('generate');
        $this->seoUrlPersister->expects($this->never())->method('updateSeoUrls');

        $this->expectExceptionObject(new \RuntimeException('Default templates not configured'));
        $seoUrlUpdater->update(ProductPageSeoUrlRoute::ROUTE_NAME, []);
    }

    public function testUpdateWithoutRoute(): void
    {
        $seoUrlUpdater = $this->createSeoUrlUpdater();

        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'salesChannelId' => Uuid::randomHex(),
                'languageId' => Uuid::randomHex(),
            ],
        ]);

        $this->connection->method('fetchAllKeyValue')->willReturn(
            [
                '' => '{{ product.translated.name }}/{{ product.productNumber }}',
            ]
        );

        $this->seoUrlGenerator->expects($this->never())->method('generate');
        $this->seoUrlPersister->expects($this->never())->method('updateSeoUrls');
        $this->expectExceptionObject(SeoException::seoUrlRouteNotFound('test'));

        $seoUrlUpdater->update('test', []);
    }

    public function testUpdateWithOutSalesChannel(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'salesChannelId' => Uuid::randomHex(),
                'languageId' => Uuid::randomHex(),
            ],
        ]);

        $this->connection->method('fetchAllKeyValue')->willReturn(
            [
                '' => '{{ product.translated.name }}/{{ product.productNumber }}',
            ]
        );

        $seoUrlUpdater = $this->createSeoUrlUpdater(
            [
                new LanguageCollection([]),
            ],
            [
                new SalesChannelCollection([]),
            ],
            [
                new ProductPageSeoUrlRoute(new ProductDefinition()),
            ]
        );

        $this->seoUrlGenerator->expects($this->never())->method('generate');
        $this->seoUrlPersister->expects($this->never())->method('updateSeoUrls');

        $seoUrlUpdater->update(ProductPageSeoUrlRoute::ROUTE_NAME, []);
    }

    public function testUpdateGetPersisted(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'salesChannelId' => 'testSalsesChannelId',
                'languageId' => 'testLanguageId',
            ],
        ]);

        $this->connection->method('fetchAllKeyValue')->willReturn(
            [
                '' => '{{ product.translated.name }}/{{ product.productNumber }}',
            ]
        );

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('testSalsesChannelId');

        $language = new LanguageEntity();
        $language->setId('testLanguageId');

        $seoUrlUpdater = $this->createSeoUrlUpdater(
            [
                new LanguageCollection([
                    $language,
                ]),
            ],
            [
                new SalesChannelCollection([
                    $salesChannel,
                ]),
            ],
            [
                new ProductPageSeoUrlRoute(new ProductDefinition()),
            ]
        );

        $this->seoUrlGenerator->expects($this->once())->method('generate');
        $this->seoUrlPersister->expects($this->once())->method('updateSeoUrls');

        $seoUrlUpdater->update(ProductPageSeoUrlRoute::ROUTE_NAME, []);
    }

    public function testResetToTemplateForceUpdatesTheGeneratedUrlWithoutWriteProtection(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['salesChannelId' => 'sales-channel-id', 'languageId' => 'language-id'],
            ['salesChannelId' => 'sales-channel-id', 'languageId' => 'other-language-id'],
            ['salesChannelId' => 'other-sales-channel-id', 'languageId' => 'language-id'],
        ]);
        $this->connection->method('fetchAllKeyValue')->willReturn(['' => '{{ product.productNumber }}']);

        $generatedUrl = new SeoUrlEntity();
        $generatedUrl->setSeoPathInfo('SW-1');
        $generatedUrl->setIsModified(true);

        $this->seoUrlGenerator->expects($this->once())->method('generate')
            ->with(['product-id'], '{{ product.productNumber }}', static::anything(), static::anything(), static::callback(
                static fn (SalesChannelEntity $salesChannel): bool => $salesChannel->getId() === 'sales-channel-id'
            ))
            ->willReturn([$generatedUrl]);
        $this->seoUrlPersister->expects($this->never())->method('updateSeoUrls');
        $this->seoUrlPersister->expects($this->once())->method('forceUpdateSeoUrls')
            ->with(static::anything(), ProductPageSeoUrlRoute::ROUTE_NAME, ['product-id'], [$generatedUrl], static::anything());

        $reset = $this->createSeoUrlUpdaterForReset()
            ->resetToTemplate(ProductPageSeoUrlRoute::ROUTE_NAME, 'PRODUCT-ID', 'SALES-CHANNEL-ID', 'LANGUAGE-ID');

        static::assertTrue($reset);
        static::assertFalse($generatedUrl->getIsModified());
    }

    public function testResetToTemplateWritesNothingWhenTheSalesChannelHasNoDomainInTheLanguage(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['salesChannelId' => 'sales-channel-id', 'languageId' => 'other-language-id'],
        ]);
        $this->connection->method('fetchAllKeyValue')->willReturn(['' => '{{ product.productNumber }}']);

        $this->seoUrlGenerator->expects($this->never())->method('generate');
        $this->seoUrlPersister->expects($this->never())->method('forceUpdateSeoUrls');

        static::assertFalse(
            $this->createSeoUrlUpdater([], [], [new ProductPageSeoUrlRoute(new ProductDefinition())])
                ->resetToTemplate(ProductPageSeoUrlRoute::ROUTE_NAME, 'product-id', 'sales-channel-id', 'language-id')
        );
    }

    public function testResetToTemplateWritesNothingWhenTheTemplateGeneratesNoUsableUrl(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['salesChannelId' => 'sales-channel-id', 'languageId' => 'language-id'],
        ]);
        $this->connection->method('fetchAllKeyValue')->willReturn(['' => '{{ product.productNumber }}']);

        $failedUrl = new SeoUrlEntity();
        $failedUrl->setSeoPathInfo('SW-1');
        $failedUrl->setError('Category is not available for sales channel');
        $emptyUrl = new SeoUrlEntity();
        $emptyUrl->setSeoPathInfo('');

        $this->seoUrlGenerator->expects($this->once())->method('generate')->willReturn([$failedUrl, $emptyUrl]);
        $this->seoUrlPersister->expects($this->never())->method('forceUpdateSeoUrls');

        static::assertFalse(
            $this->createSeoUrlUpdaterForReset()
                ->resetToTemplate(ProductPageSeoUrlRoute::ROUTE_NAME, 'product-id', 'sales-channel-id', 'language-id')
        );
    }

    private function createSeoUrlUpdaterForReset(): SeoUrlUpdater
    {
        $language = new LanguageEntity();
        $language->setId('language-id');

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-id');

        return $this->createSeoUrlUpdater(
            [new LanguageCollection([$language])],
            [new SalesChannelCollection([$salesChannel])],
            [new ProductPageSeoUrlRoute(new ProductDefinition())]
        );
    }

    /**
     * @param LanguageCollection[] $languageSearches
     * @param SalesChannelCollection[] $salesChannelSearches
     * @param SeoUrlRouteInterface[] $seoUrlRoutes
     */
    private function createSeoUrlUpdater(
        array $languageSearches = [],
        array $salesChannelSearches = [],
        array $seoUrlRoutes = []
    ): SeoUrlUpdater {
        $languageRepository = new StaticEntityRepository($languageSearches);
        $this->seoUrlRouteRegistry = new SeoUrlRouteRegistry($seoUrlRoutes);
        $salesChannelRepository = new StaticEntityRepository($salesChannelSearches);

        return new SeoUrlUpdater(
            $languageRepository,
            $this->seoUrlRouteRegistry,
            $this->seoUrlGenerator,
            $this->seoUrlPersister,
            $this->connection,
            $salesChannelRepository
        );
    }
}
