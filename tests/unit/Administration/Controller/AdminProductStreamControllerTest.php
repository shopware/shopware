<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdminProductStreamController;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdminProductStreamController::class)]
class AdminProductStreamControllerTest extends TestCase
{
    private MockObject&RequestCriteriaBuilder $requestCriteriaBuilder;

    private Stub&SalesChannelContextServiceInterface $salesChannelContextService;

    /**
     * @var MockObject&SalesChannelRepository<ProductCollection>
     */
    private MockObject&SalesChannelRepository $salesChannelRepository;

    private Stub&ProductDefinition $productDefinition;

    protected function setUp(): void
    {
        $this->productDefinition = static::createStub(ProductDefinition::class);
        $this->salesChannelRepository = $this->createMock(SalesChannelRepository::class);
        $this->salesChannelContextService = static::createStub(SalesChannelContextServiceInterface::class);
        $this->requestCriteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
    }

    public function testProductStreamPreview(): void
    {
        $context = Context::createDefaultContext();
        $controller = new AdminProductStreamController(
            $this->productDefinition,
            $this->salesChannelRepository,
            $this->salesChannelContextService,
            $this->requestCriteriaBuilder,
        );

        $this->requestCriteriaBuilder->expects($this->once())->method('handleRequest')->willReturn(new Criteria());

        $this->salesChannelRepository->expects($this->once())->method('search')
            ->willReturnCallback(static function (Criteria $criteria, SalesChannelContext $context) {
                static::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $criteria->getTotalCountMode());
                static::assertTrue($criteria->hasAssociation('manufacturer'));
                static::assertTrue($criteria->hasAssociation('options'));
                static::assertTrue($criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
                static::assertCount(1, $criteria->getFilters());
                static::assertInstanceOf(ProductAvailableFilter::class, $criteria->getFilters()[0]);
                static::assertCount(0, $criteria->getGroupFields());

                return new EntitySearchResult(
                    'product',
                    1,
                    new ProductCollection(),
                    null,
                    $criteria,
                    $context->getContext()
                );
            });

        $response = $controller->productStreamPreview('salesChannelId', new Request(), $context);
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString(
            '{"extensions":[],"elements":[],"aggregations":[],"page":1,"limit":null,"entity":"product","total":1,"states":[]}',
            $response->getContent()
        );
    }

    public function testProductStreamPreviewAppliesGroupingWhenDisplayAsGroupRequested(): void
    {
        $context = Context::createDefaultContext();
        $controller = new AdminProductStreamController(
            $this->productDefinition,
            $this->salesChannelRepository,
            $this->salesChannelContextService,
            $this->requestCriteriaBuilder,
        );

        $this->requestCriteriaBuilder->expects($this->once())->method('handleRequest')->willReturn(new Criteria());

        $this->salesChannelRepository->expects($this->once())->method('search')
            ->willReturnCallback(static function (Criteria $criteria, SalesChannelContext $context) {
                // grouping mirrors the storefront listing: group by displayGroup and drop the null group
                static::assertContainsEquals(new FieldGrouping('displayGroup'), $criteria->getGroupFields());
                static::assertContainsEquals(new NotEqualsFilter('displayGroup', null), $criteria->getFilters());

                return new EntitySearchResult(
                    'product',
                    0,
                    new ProductCollection(),
                    null,
                    $criteria,
                    $context->getContext()
                );
            });

        $response = $controller->productStreamPreview(
            'salesChannelId',
            new Request(['displayAsGroup' => '1']),
            $context
        );

        static::assertNotFalse($response->getContent());
    }

    /**
     * @param list<string> $loadableIds
     * @param list<string> $expectedReadIds
     */
    #[DataProvider('presentationProductProvider')]
    public function testGroupedPreviewResolvesPresentationProduct(
        ?VariantListingConfig $variantListingConfig,
        array $loadableIds,
        array $expectedReadIds,
        string $expectedId
    ): void {
        $context = Context::createDefaultContext();
        $controller = new AdminProductStreamController(
            $this->productDefinition,
            $this->salesChannelRepository,
            $this->salesChannelContextService,
            $this->requestCriteriaBuilder,
        );

        $this->requestCriteriaBuilder->method('handleRequest')->willReturn(new Criteria());

        $matched = $this->createProduct('matched-variant', 'parent', $variantListingConfig);
        $products = [
            'main-variant' => $this->createProduct('main-variant', 'parent', $variantListingConfig),
            'parent' => $this->createProduct('parent', null, $variantListingConfig),
        ];

        $readIds = [];
        $this->salesChannelRepository->expects($this->exactly($expectedReadIds === [] ? 1 : 2))->method('search')
            ->willReturnCallback(static function (Criteria $criteria, SalesChannelContext $salesChannelContext) use ($matched, $products, $loadableIds, &$readIds) {
                if ($criteria->getIds() === []) {
                    return new EntitySearchResult('product', 1, new ProductCollection([$matched]), null, $criteria, $salesChannelContext->getContext());
                }

                $readIds = $criteria->getIds();
                $loaded = array_values(array_intersect_key($products, array_flip($loadableIds)));

                return new EntitySearchResult('product', \count($loaded), new ProductCollection($loaded), null, $criteria, $salesChannelContext->getContext());
            });

        $response = $controller->productStreamPreview('salesChannelId', new Request(['displayAsGroup' => '1']), $context);

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($expectedReadIds, $readIds);
        static::assertSame(1, $content['total']);
        static::assertCount(1, $content['elements']);
        static::assertSame($expectedId, array_values($content['elements'])[0]['id']);
    }

    public static function presentationProductProvider(): \Generator
    {
        yield 'no presentation config keeps the matched variant' => [
            null,
            [],
            [],
            'matched-variant',
        ];

        yield 'main variant replaces the matched variant' => [
            new VariantListingConfig(false, 'main-variant', null),
            ['main-variant'],
            ['main-variant'],
            'main-variant',
        ];

        yield 'display parent replaces the matched variant' => [
            new VariantListingConfig(true, null, null),
            ['parent'],
            ['parent'],
            'parent',
        ];

        yield 'main variant that cannot be loaded (deleted or inactive) keeps the matched variant' => [
            new VariantListingConfig(false, 'main-variant', null),
            [],
            ['main-variant'],
            'matched-variant',
        ];

        yield 'matched variant being the main variant needs no read' => [
            new VariantListingConfig(false, 'matched-variant', null),
            [],
            [],
            'matched-variant',
        ];
    }

    private function createProduct(string $id, ?string $parentId, ?VariantListingConfig $variantListingConfig): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->internalSetEntityData(ProductDefinition::ENTITY_NAME, new FieldVisibility([]));
        $product->setId($id);
        $product->setParentId($parentId);
        $product->setVariantListingConfig($variantListingConfig);

        return $product;
    }
}
