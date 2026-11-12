<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\Extension\ProductDocumentRouteExtension;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentRoute;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentRouteResponse;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentRoute::class)]
class ProductDocumentRouteTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $tags = [];

    /**
     * @var list<Criteria>
     */
    private array $aggregatedCriteria = [];

    /**
     * @var list<Criteria>
     */
    private array $documentCriteria = [];

    public function testLoadSearchesTheDocumentsWithAPublicFile(): void
    {
        $variantId = Uuid::randomHex();
        $productCriteria = null;
        $documentRepository = $this->createDocumentRepository(['media-1', 'media-2'], [new ProductDocumentCollection()]);
        $mediaRepository = new StaticEntityRepository([new MediaCollection([$this->createMedia('media-1')])]);

        $this->createRoute(
            [static function (Criteria $criteria) use (&$productCriteria, $variantId): array {
                $productCriteria = $criteria;

                return [self::createProduct($variantId, null)];
            }],
            $documentRepository,
            $mediaRepository,
        )->load($variantId, new Request(), Generator::generateSalesChannelContext(), new Criteria());

        static::assertInstanceOf(Criteria::class, $productCriteria);
        static::assertSame([$variantId], $productCriteria->getIds());
        static::assertSame(['parentId'], $productCriteria->getFields());

        static::assertEquals([new EqualsFilter('product.id', $variantId)], $this->aggregatedCriteria[0]->getFilters());

        static::assertSame(['media-1', 'media-2'], $mediaRepository->searchedCriteria[0]->getIds());
        static::assertEquals([
            new EqualsFilter('private', false),
            new NotEqualsFilter('path', null),
        ], $mediaRepository->searchedCriteria[0]->getFilters());

        $documentCriteria = $this->documentCriteria[0];
        static::assertEquals([new EqualsFilter('product.id', $variantId), new EqualsAnyFilter('mediaId', ['media-1'])], $documentCriteria->getFilters());
        static::assertEquals([new FieldSorting('position'), new FieldSorting('id')], $documentCriteria->getSorting());
    }

    public function testLoadDropsTheProductAndTheFilesOfTheTranslations(): void
    {
        $productId = Uuid::randomHex();
        $criteria = new Criteria();
        $criteria->addAssociation('product.productReviews');
        $criteria->addAssociation('translations.media');
        $criteria->addFields(['id']);

        $this->createRoute(
            [static fn (): array => [self::createProduct($productId, null)]],
            $this->createDocumentRepository(['media-1'], [new ProductDocumentCollection()]),
            new StaticEntityRepository([new MediaCollection([$this->createMedia('media-1')])]),
        )->load($productId, new Request(), Generator::generateSalesChannelContext(), $criteria);

        $documentCriteria = $this->documentCriteria[0];
        static::assertFalse($documentCriteria->hasAssociation('product'));
        static::assertTrue($documentCriteria->hasAssociation('translations'));
        static::assertFalse($documentCriteria->getAssociation('translations')->hasAssociation('media'));
        static::assertSame([], $documentCriteria->getFields());
    }

    public function testLoadKeepsTheRequestedSorting(): void
    {
        $productId = Uuid::randomHex();
        $documentRepository = $this->createDocumentRepository(['media-1'], [new ProductDocumentCollection()]);
        $criteria = (new Criteria())->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $this->createRoute(
            [[self::createProduct($productId, null)]],
            $documentRepository,
            new StaticEntityRepository([new MediaCollection([$this->createMedia('media-1')])]),
        )->load($productId, new Request(), Generator::generateSalesChannelContext(), $criteria);

        static::assertEquals([new FieldSorting('createdAt', FieldSorting::DESCENDING)], $this->documentCriteria[0]->getSorting());
    }

    public function testLoadAssignsTheFilesToTheDocuments(): void
    {
        $productId = Uuid::randomHex();
        $first = $this->createDocument('media-1');
        $second = $this->createDocument('media-2');
        $sharingTheFile = $this->createDocument('media-1');
        $firstMedia = $this->createMedia('media-1');
        $secondMedia = $this->createMedia('media-2');

        $result = $this->createRoute(
            [[self::createProduct($productId, null)]],
            $this->createDocumentRepository(['media-1', 'media-2'], [new ProductDocumentCollection([$first, $second, $sharingTheFile])]),
            new StaticEntityRepository([new MediaCollection([$firstMedia, $secondMedia])]),
        )->load($productId, new Request(), Generator::generateSalesChannelContext(), new Criteria())->getResult();

        static::assertSame([$first, $second, $sharingTheFile], array_values($result->getEntities()->getElements()));
        static::assertSame(3, $result->getTotal());
        static::assertSame($firstMedia, $first->getMedia());
        static::assertSame($secondMedia, $second->getMedia());
        static::assertSame($firstMedia, $sharingTheFile->getMedia());
    }

    public function testLoadMatchesNoDocumentWhenNoFileIsPublic(): void
    {
        $productId = Uuid::randomHex();
        $criteria = new Criteria();
        $criteria->addAggregation(new CountAggregation('count', 'id'));

        $this->createRoute(
            [[self::createProduct($productId, null)]],
            $this->createDocumentRepository(['media-1'], [new ProductDocumentCollection()]),
            new StaticEntityRepository([new MediaCollection()]),
        )->load($productId, new Request(), Generator::generateSalesChannelContext(), $criteria);

        $documentCriteria = $this->documentCriteria[0];
        static::assertEquals([new EqualsFilter('product.id', $productId), new EqualsAnyFilter('mediaId', [])], $documentCriteria->getFilters());
        static::assertNotNull($documentCriteria->getAggregation('count'));
    }

    public function testLoadSkipsTheMediaSearchWithoutDocuments(): void
    {
        $productId = Uuid::randomHex();
        $mediaRepository = new StaticEntityRepository([]);

        $result = $this->createRoute(
            [[self::createProduct($productId, null)]],
            $this->createDocumentRepository([], []),
            $mediaRepository,
        )->load($productId, new Request(), Generator::generateSalesChannelContext(), new Criteria())->getDocuments();

        static::assertCount(0, $result);
        static::assertSame([], $mediaRepository->searchedCriteria);
    }

    public function testLoadTagsTheResponseWithTheParentProduct(): void
    {
        $variantId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $this->createRoute([[self::createProduct($variantId, $parentId)]])
            ->load($variantId, new Request(), Generator::generateSalesChannelContext(), new Criteria());

        static::assertSame([ProductDetailRoute::buildName($parentId)], $this->tags);
    }

    public function testLoadThrowsForAProductThatIsNotVisible(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->createRoute([new EntityCollection()])
            ->load(Uuid::randomHex(), new Request(), Generator::generateSalesChannelContext(), new Criteria());
    }

    public function testLoadThrowsForAnInvalidProductId(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->createRoute()->load('not-a-uuid', new Request(), Generator::generateSalesChannelContext(), new Criteria());
    }

    public function testLoadPublishesTheExtension(): void
    {
        $productId = Uuid::randomHex();
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(ProductDocumentRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ProductDocumentRouteExtension::onPre(), static function (ProductDocumentRouteExtension $extension) use ($productId, $request, $context, $criteria, $response): void {
            static::assertSame(['productId' => $productId, 'request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        static::assertSame($response, $this->createRoute(dispatcher: $dispatcher)->load($productId, $request, $context, $criteria));
    }

    /**
     * @param array<mixed> $productSearches
     * @param EntityRepository<ProductDocumentCollection>|null $documentRepository
     * @param StaticEntityRepository<MediaCollection>|null $mediaRepository
     */
    private function createRoute(
        array $productSearches = [],
        ?EntityRepository $documentRepository = null,
        ?StaticEntityRepository $mediaRepository = null,
        EventDispatcher $dispatcher = new EventDispatcher(),
    ): ProductDocumentRoute {
        $dispatcher->addListener(AddCacheTagEvent::class, function (AddCacheTagEvent $event): void {
            $this->tags = [...$this->tags, ...array_values($event->tags)];
        });

        /** @var StaticSalesChannelRepository<ProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository($productSearches);

        return new ProductDocumentRoute(
            $productRepository,
            $documentRepository ?? $this->createDocumentRepository([], []),
            $mediaRepository ?? new StaticEntityRepository([]),
            new CacheTagCollector(new RequestStack(), $dispatcher),
            new ExtensionDispatcher($dispatcher),
        );
    }

    /**
     * @param list<string> $mediaIds
     * @param list<ProductDocumentCollection> $searches
     *
     * @return EntityRepository<ProductDocumentCollection>
     */
    private function createDocumentRepository(array $mediaIds, array $searches): EntityRepository
    {
        $aggregation = new TermsResult('media-ids', array_map(static fn (string $id): Bucket => new Bucket($id, 1, null), $mediaIds));

        /** @var EntityRepository<ProductDocumentCollection>&Stub $repository */
        $repository = static::createStub(EntityRepository::class);
        $repository->method('aggregate')->willReturnCallback(function (Criteria $criteria) use ($aggregation): AggregationResultCollection {
            $this->aggregatedCriteria[] = clone $criteria;

            return new AggregationResultCollection([$aggregation]);
        });
        $repository->method('search')->willReturnCallback(function (Criteria $criteria, Context $context) use (&$searches): EntitySearchResult {
            $this->documentCriteria[] = clone $criteria;
            $documents = array_shift($searches) ?? new ProductDocumentCollection();

            return new EntitySearchResult('product_document', $documents->count(), $documents, null, $criteria, $context);
        });

        return $repository;
    }

    private static function createProduct(string $id, ?string $parentId): PartialEntity
    {
        $product = new PartialEntity();
        $product->set('id', $id);
        $product->set('parentId', $parentId);

        return $product;
    }

    private function createDocument(string $mediaId): ProductDocumentEntity
    {
        $document = new ProductDocumentEntity();
        $document->setId(Uuid::randomHex());
        $document->setTranslated(['mediaId' => $mediaId]);

        return $document;
    }

    private function createMedia(string $id): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($id);

        return $media;
    }
}
