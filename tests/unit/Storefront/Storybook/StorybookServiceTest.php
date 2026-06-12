<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Storybook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
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
#[CoversClass(StorybookService::class)]
class StorybookServiceTest extends TestCase
{
    /**
     * @var SalesChannelRepository<SalesChannelProductCollection>&MockObject
     */
    private SalesChannelRepository&MockObject $productRepository;

    /**
     * @var EntityRepository<MediaCollection>&MockObject
     */
    private EntityRepository&MockObject $mediaRepository;

    /**
     * @var EntityRepository<SalesChannelCollection>&MockObject
     */
    private EntityRepository&MockObject $salesChannelRepository;

    private AbstractSalesChannelContextFactory&MockObject $contextFactory;

    private DatabaseSalesChannelThemeLoader&MockObject $themeLoader;

    private ThemeRuntimeConfigStorage&MockObject $themeRuntimeConfigStorage;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(SalesChannelRepository::class);
        $this->mediaRepository = $this->createMock(EntityRepository::class);
        $this->salesChannelRepository = $this->createMock(EntityRepository::class);
        $this->contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $this->themeLoader = $this->createMock(DatabaseSalesChannelThemeLoader::class);
        $this->themeRuntimeConfigStorage = $this->createMock(ThemeRuntimeConfigStorage::class);
    }

    public function testCreateSalesChannelContextReturnsSalesChannelContext(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $this->salesChannelRepository->method('searchIds')
            ->willReturn($this->createSalesChannelIdSearchResult($salesChannelId));

        $this->contextFactory->method('create')
            ->with('', $salesChannelId)
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
            ->with('sales-channel-id')
            ->willReturn(['Storefront']);

        $this->themeRuntimeConfigStorage->method('getThemeIdByTechnicalName')
            ->with('Storefront')
            ->willReturn('theme-id-123');

        $result = $this->createService()->getThemeId('sales-channel-id');

        static::assertSame('theme-id-123', $result);
    }

    public function testGetThemeIdReturnsNullWhenThemeLoaderReturnsEmpty(): void
    {
        $this->themeLoader->method('load')->willReturn([]);

        $this->themeRuntimeConfigStorage->expects($this->never())
            ->method('getThemeIdByTechnicalName');

        $result = $this->createService()->getThemeId('sales-channel-id');

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

    private function createService(): StorybookService
    {
        return new StorybookService(
            $this->productRepository,
            $this->mediaRepository,
            $this->salesChannelRepository,
            $this->contextFactory,
            $this->themeLoader,
            $this->themeRuntimeConfigStorage,
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
