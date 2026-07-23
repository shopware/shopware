<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Storybook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Storybook\StorybookService;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopware\Storefront\Theme\ThemeRuntimeConfigStorage;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorybookService::class)]
class StorybookServiceTest extends TestCase
{
    /**
     * @var SalesChannelRepository<SalesChannelProductCollection>&Stub
     */
    private SalesChannelRepository&Stub $productRepository;

    /**
     * @var EntityRepository<MediaCollection>&Stub
     */
    private EntityRepository&Stub $mediaRepository;

    /**
     * @var EntityRepository<SalesChannelCollection>&Stub
     */
    private EntityRepository&Stub $salesChannelRepository;

    private AbstractSalesChannelContextFactory&Stub $contextFactory;

    private DatabaseSalesChannelThemeLoader&Stub $themeLoader;

    private ThemeRuntimeConfigStorage&Stub $themeRuntimeConfigStorage;

    protected function setUp(): void
    {
        $this->productRepository = static::createStub(SalesChannelRepository::class);
        $this->mediaRepository = static::createStub(EntityRepository::class);
        $this->salesChannelRepository = static::createStub(EntityRepository::class);
        $this->contextFactory = static::createStub(AbstractSalesChannelContextFactory::class);
        $this->themeLoader = static::createStub(DatabaseSalesChannelThemeLoader::class);
        $this->themeRuntimeConfigStorage = static::createStub(ThemeRuntimeConfigStorage::class);
    }

    public function testCreateSalesChannelContextReturnsSalesChannelContext(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->salesChannelRepository->method('searchIds')
            ->willReturn($this->createSalesChannelIdSearchResult($salesChannelId));

        $this->contextFactory->method('create')
            ->willReturn($salesChannelContext);

        $result = $this->createService()->createSalesChannelContext();

        static::assertSame($salesChannelContext, $result);
    }

    public function testCreateSalesChannelContextThrowsWhenNoSalesChannelAvailable(): void
    {
        $this->salesChannelRepository->method('searchIds')
            ->willReturn(new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()));

        $this->expectException(SalesChannelException::class);

        $this->createService()->createSalesChannelContext();
    }

    public function testGetThemeIdReturnsThemeIdFromTechnicalName(): void
    {
        $this->themeLoader->method('load')
            ->willReturn(['Storefront']);

        $this->themeRuntimeConfigStorage->method('getThemeIdByTechnicalName')
            ->willReturn('theme-id-123');

        $result = $this->createService()->getThemeId('sales-channel-id');

        static::assertSame('theme-id-123', $result);
    }

    public function testGetThemeIdReturnsNullWhenThemeLoaderReturnsEmpty(): void
    {
        $this->themeLoader->method('load')->willReturn([]);

        $themeRuntimeConfigStorage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $themeRuntimeConfigStorage->expects($this->never())
            ->method('getThemeIdByTechnicalName');

        $result = $this->createService($themeRuntimeConfigStorage)->getThemeId('sales-channel-id');

        static::assertNull($result);
    }

    public function testResolveComponentPropsFiltersDenyListedQueryParams(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $request = new Request([
            'label' => 'Click me',
            'measureEnabled' => 'true',
            'backgrounds' => 'dark',
            'outline' => '1',
            'viewport' => 'mobile',
        ]);

        $result = $this->createService()->resolveComponentProps($request, $salesChannelContext);

        static::assertArrayHasKey('label', $result);
        static::assertSame('Click me', $result['label']);
        static::assertArrayNotHasKey('measureEnabled', $result);
        static::assertArrayNotHasKey('backgrounds', $result);
        static::assertArrayNotHasKey('outline', $result);
        static::assertArrayNotHasKey('viewport', $result);
    }

    public function testResolveComponentPropsFiltersInvalidQueryParamIdentifiers(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $request = new Request([
            'validProp' => 'hello',
            '123invalid' => 'bad',
            'also-invalid' => 'bad',
            'valid_prop2' => 'world',
        ]);

        $result = $this->createService()->resolveComponentProps($request, $salesChannelContext);

        static::assertArrayHasKey('validProp', $result);
        static::assertArrayHasKey('valid_prop2', $result);
        static::assertArrayNotHasKey('123invalid', $result);
        static::assertArrayNotHasKey('also-invalid', $result);
    }

    public function testResolveComponentPropsResolvesProductEntityProperty(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $product = new SalesChannelProductEntity();
        $product->setId('product-id-123');
        $product->setUniqueIdentifier('product-id-123');

        $this->productRepository->method('search')
            ->willReturn(new EntitySearchResult(
                ProductDefinition::ENTITY_NAME,
                1,
                new SalesChannelProductCollection([$product]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $result = $this->createService()->resolveComponentProps(
            new Request(['product' => 'product']),
            $salesChannelContext
        );

        static::assertArrayHasKey('product', $result);
        static::assertSame($product, $result['product']);
    }

    public function testResolveComponentPropsResolvesMediaEntityProperty(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $media = new MediaEntity();
        $media->setId('media-id-123');
        $media->setUniqueIdentifier('media-id-123');

        $this->mediaRepository->method('search')
            ->willReturn(new EntitySearchResult(
                MediaDefinition::ENTITY_NAME,
                1,
                new MediaCollection([$media]),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $result = $this->createService()->resolveComponentProps(
            new Request(['media' => 'media']),
            $salesChannelContext
        );

        static::assertArrayHasKey('media', $result);
        static::assertSame($media, $result['media']);
    }

    public function testResolveComponentPropsReturnsNullForProductWhenRepositoryIsEmpty(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $this->productRepository->method('search')
            ->willReturn(new EntitySearchResult(
                ProductDefinition::ENTITY_NAME,
                0,
                new SalesChannelProductCollection(),
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            ));

        $result = $this->createService()->resolveComponentProps(
            new Request(['product' => 'product']),
            $salesChannelContext
        );

        static::assertArrayHasKey('product', $result);
        static::assertNull($result['product']);
    }

    private function createService(?ThemeRuntimeConfigStorage $themeRuntimeConfigStorage = null): StorybookService
    {
        return new StorybookService(
            $this->productRepository,
            $this->mediaRepository,
            $this->salesChannelRepository,
            $this->contextFactory,
            $this->themeLoader,
            $themeRuntimeConfigStorage ?? $this->themeRuntimeConfigStorage,
        );
    }

    private function createSalesChannelIdSearchResult(string $salesChannelId): IdSearchResult
    {
        return new IdSearchResult(
            1,
            [$salesChannelId => ['primaryKey' => $salesChannelId, 'data' => []]],
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
