<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductDescriptionReviewsStruct;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\Cms\ProductDescriptionReviewsCmsElementResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductDescriptionReviewsCmsElementResolver::class)]
class ProductDescriptionReviewsCmsElementResolverTest extends TestCase
{
    private StaticSystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService([
            'core.listing.showReview' => true,
        ]);
    }

    public function testGetType(): void
    {
        $resolver = $this->getResolver();

        static::assertSame('product-description-reviews', $resolver->getType());
    }

    public function testCollect(): void
    {
        $resolverContext = new ResolverContext(static::createStub(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType(ProductDescriptionReviewsCmsElementResolver::TYPE);

        $collection = $this->getResolver()->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichSlotWithProductDescriptionReviews(): void
    {
        // global setting enabled
        $this->systemConfigService->set('core.listing.showReview', true);

        $resolver = $this->getResolver();

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request([
            'success' => true,
        ]));

        $productId = 'product-1';
        $config = new FieldConfigCollection([
            new FieldConfig('product', FieldConfig::SOURCE_STATIC, $productId),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new SalesChannelProductCollection([$product]));

        $data = new ElementDataCollection();
        $data->add('product_slot-1', $result);

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(ProductDescriptionReviewsStruct::class, $data);
        static::assertTrue($data->getRatingSuccess());

        $reviews = $data->getReviews();
        static::assertInstanceOf(ProductReviewResult::class, $reviews);
        static::assertSame($product, $data->getProduct());
        static::assertSame($productId, $reviews->getProductId());
    }

    public function testEnrichSlotWithProductDescriptionReviewsForCurrentSalesChannel(): void
    {
        $resolver = $this->getResolver();

        $context = Generator::generateSalesChannelContext();

        // global setting disabled
        $this->systemConfigService->set('core.listing.showReview', false);
        // but enabled for current sales channel
        $this->systemConfigService->set('core.listing.showReview', true, $context->getSalesChannelId());

        $context = new ResolverContext($context, new Request([
            'success' => true,
        ]));

        $productId = 'product-1';
        $config = new FieldConfigCollection([
            new FieldConfig('product', FieldConfig::SOURCE_STATIC, $productId),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new SalesChannelProductCollection([$product]));

        $data = new ElementDataCollection();
        $data->add('product_slot-1', $result);

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(ProductDescriptionReviewsStruct::class, $data);
        static::assertTrue($data->getRatingSuccess());

        $reviews = $data->getReviews();
        static::assertInstanceOf(ProductReviewResult::class, $reviews);
        static::assertSame($product, $data->getProduct());
        static::assertSame($productId, $reviews->getProductId());
    }

    public function testEnrichSlotWithProductReviewsDisabled(): void
    {
        $resolver = $this->getResolver();

        $this->systemConfigService->set('core.listing.showReview', false);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request([
            'success' => true,
        ]));

        $productId = 'product-1';
        $config = new FieldConfigCollection([
            new FieldConfig('product', FieldConfig::SOURCE_STATIC, $productId),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new SalesChannelProductCollection([$product]));

        $data = new ElementDataCollection();
        $data->add('product_slot-1', $result);

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(ProductDescriptionReviewsStruct::class, $data);
        static::assertTrue($data->getRatingSuccess());

        static::assertNull($data->getReviews());
        static::assertSame($product, $data->getProduct());
    }

    public function testEnrichSlotWithProductReviewsDisabledForCurrentSalesChannel(): void
    {
        $resolver = $this->getResolver();

        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $context = new ResolverContext($salesChannelContext, new Request([
            'success' => true,
        ]));

        // global setting enabled
        $this->systemConfigService->set('core.listing.showReview', true);
        // but disabled for current sales channel
        $this->systemConfigService->set('core.listing.showReview', false, $salesChannelId);

        $productId = 'product-1';
        $config = new FieldConfigCollection([
            new FieldConfig('product', FieldConfig::SOURCE_STATIC, $productId),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new SalesChannelProductCollection([$product]));

        $data = new ElementDataCollection();
        $data->add('product_slot-1', $result);

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(ProductDescriptionReviewsStruct::class, $data);
        static::assertTrue($data->getRatingSuccess());

        static::assertNull($data->getReviews());
        static::assertSame($product, $data->getProduct());
    }

    public function testEnrichSetsEmptyDataWithoutConfig(): void
    {
        $resolver = $this->getResolver();

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $data = new ElementDataCollection();

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(ProductDescriptionReviewsStruct::class, $data);
        static::assertNull($data->getReviews());
        static::assertNull($data->getProduct());
    }

    public function testEnrichLoadsTheDocumentsOfTheResolvedProductThroughTheRoute(): void
    {
        $variantId = Uuid::randomHex();
        $document = new ProductDocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setTranslated(['mediaId' => 'media-1']);
        $media = new MediaEntity();
        $media->setId('media-1');

        $route = $this->createDocumentRoute(new ProductDocumentCollection([$document]), new MediaCollection([$media]));

        $data = $this->enrichWithProduct($this->createProduct($variantId, Uuid::randomHex()), $route);

        static::assertSame([$document], array_values($data->getDocuments()?->getElements() ?? []));
        static::assertSame($media, $document->getMedia());
    }

    private function enrichWithProduct(
        SalesChannelProductEntity $product,
        ProductDocumentRoute $documentRoute,
    ): ProductDescriptionReviewsStruct {
        $this->systemConfigService->set('core.listing.showReview', false);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('product', FieldConfig::SOURCE_STATIC, $product->getParentId() ?? $product->getId()),
        ]));

        $productResult = static::createStub(EntitySearchResult::class);
        $productResult->method('getEntities')->willReturn(new SalesChannelProductCollection([$product]));

        $elementData = new ElementDataCollection();
        $elementData->add('product_slot-1', $productResult);

        $this->getResolver($documentRoute)->enrich(
            $slot,
            new ResolverContext(Generator::generateSalesChannelContext(), new Request()),
            $elementData
        );

        $data = $slot->getData();
        static::assertInstanceOf(ProductDescriptionReviewsStruct::class, $data);

        return $data;
    }

    private function createDocumentRoute(ProductDocumentCollection $documents, MediaCollection $media): ProductDocumentRoute
    {
        $dispatcher = new EventDispatcher();

        /** @var StaticSalesChannelRepository<ProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([]);

        return new ProductDocumentRoute(
            $productRepository,
            $this->createDocumentRepository($documents),
            new StaticEntityRepository([$media]),
            new CacheTagCollector(new RequestStack(), $dispatcher),
            new ExtensionDispatcher($dispatcher),
        );
    }

    /**
     * @return EntityRepository<ProductDocumentCollection>
     */
    private function createDocumentRepository(ProductDocumentCollection $documents): EntityRepository
    {
        $mediaIds = array_values(array_filter(
            $documents->map(static fn (ProductDocumentEntity $document): mixed => $document->getTranslation('mediaId')),
            \is_string(...)
        ));
        $aggregation = new TermsResult('media-ids', array_map(static fn (string $id): Bucket => new Bucket($id, 1, null), $mediaIds));

        /** @var EntityRepository<ProductDocumentCollection>&Stub $repository */
        $repository = static::createStub(EntityRepository::class);
        $repository->method('aggregate')->willReturn(new AggregationResultCollection([$aggregation]));
        $repository->method('search')->willReturnCallback(
            static fn (Criteria $criteria, Context $context): EntitySearchResult => new EntitySearchResult('product_document', $documents->count(), $documents, null, $criteria, $context)
        );

        return $repository;
    }

    private function createProduct(string $id, ?string $parentId): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId($id);
        $product->setParentId($parentId);

        return $product;
    }

    private function getResolver(?ProductDocumentRoute $documentRoute = null): ProductDescriptionReviewsCmsElementResolver
    {
        $productReviewLoader = new ProductReviewLoader(
            static::createStub(AbstractProductReviewRoute::class),
            $this->systemConfigService,
            new EventDispatcher()
        );

        $scriptExecutor = static::createStub(ScriptExecutor::class);

        return new ProductDescriptionReviewsCmsElementResolver(
            $productReviewLoader,
            $scriptExecutor,
            $this->systemConfigService,
            $documentRoute ?? $this->createDocumentRoute(new ProductDocumentCollection(), new MediaCollection()),
        );
    }
}
