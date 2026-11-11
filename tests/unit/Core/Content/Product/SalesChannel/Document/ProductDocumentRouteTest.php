<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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

    public function testLoadSearchesTheDocumentsOfTheVisibleProduct(): void
    {
        $variantId = Uuid::randomHex();
        $productCriteria = null;
        $documentRepository = new StaticEntityRepository([new ProductDocumentCollection()]);

        $this->createRoute(
            [static function (Criteria $criteria) use (&$productCriteria, $variantId): array {
                $productCriteria = $criteria;

                return [self::createProduct($variantId, null)];
            }],
            $documentRepository,
        )->load($variantId, new Request(), Generator::generateSalesChannelContext(), new Criteria());

        static::assertInstanceOf(Criteria::class, $productCriteria);
        static::assertSame([$variantId], $productCriteria->getIds());
        static::assertInstanceOf(ProductAvailableFilter::class, $productCriteria->getFilters()[0] ?? null);

        $documentCriteria = $documentRepository->searchedCriteria[0];
        static::assertEquals([new EqualsFilter('product.id', $variantId)], $documentCriteria->getFilters());
        static::assertEquals([new FieldSorting('position')], $documentCriteria->getSorting());
    }

    public function testLoadKeepsTheRequestedSorting(): void
    {
        $productId = Uuid::randomHex();
        $documentRepository = new StaticEntityRepository([new ProductDocumentCollection()]);
        $criteria = (new Criteria())->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $this->createRoute([[self::createProduct($productId, null)]], $documentRepository)
            ->load($productId, new Request(), Generator::generateSalesChannelContext(), $criteria);

        static::assertEquals([new FieldSorting('createdAt', FieldSorting::DESCENDING)], $documentRepository->searchedCriteria[0]->getSorting());
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

    public function testLoadAssignsThePublicFilesAndDropsTheOthers(): void
    {
        $productId = Uuid::randomHex();
        $first = $this->createDocument('media-1');
        $second = $this->createDocument('media-2');
        $sharingTheFile = $this->createDocument('media-1');
        $private = $this->createDocument('media-3');

        $firstMedia = $this->createMedia('media-1');
        $secondMedia = $this->createMedia('media-2');
        $mediaRepository = new StaticEntityRepository([new MediaCollection([$firstMedia, $secondMedia])]);

        $result = $this->createRoute(
            [[self::createProduct($productId, null)]],
            new StaticEntityRepository([new ProductDocumentCollection([$first, $second, $sharingTheFile, $private])]),
            $mediaRepository,
        )->load($productId, new Request(), Generator::generateSalesChannelContext(), new Criteria())->getResult();

        static::assertSame([$first, $second, $sharingTheFile], array_values($result->getEntities()->getElements()));
        static::assertSame(3, $result->getTotal());
        static::assertSame($firstMedia, $first->getMedia());
        static::assertSame($secondMedia, $second->getMedia());
        static::assertSame($firstMedia, $sharingTheFile->getMedia());
        static::assertNull($private->getMedia());

        $mediaCriteria = $mediaRepository->searchedCriteria[0];
        static::assertSame(['media-1', 'media-2', 'media-3'], $mediaCriteria->getIds());
        static::assertEquals([new EqualsFilter('private', false)], $mediaCriteria->getFilters());
    }

    public function testLoadSkipsTheMediaSearchWithoutDocuments(): void
    {
        $productId = Uuid::randomHex();
        $mediaRepository = new StaticEntityRepository([]);

        $result = $this->createRoute(
            [[self::createProduct($productId, null)]],
            new StaticEntityRepository([new ProductDocumentCollection()]),
            $mediaRepository,
        )->load($productId, new Request(), Generator::generateSalesChannelContext(), new Criteria())->getDocuments();

        static::assertCount(0, $result);
        static::assertSame([], $mediaRepository->searchedCriteria);
    }

    /**
     * @param array<mixed> $productSearches
     * @param StaticEntityRepository<ProductDocumentCollection>|null $documentRepository
     * @param StaticEntityRepository<MediaCollection>|null $mediaRepository
     */
    private function createRoute(
        array $productSearches = [],
        ?StaticEntityRepository $documentRepository = null,
        ?StaticEntityRepository $mediaRepository = null,
    ): ProductDocumentRoute {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(AddCacheTagEvent::class, function (AddCacheTagEvent $event): void {
            $this->tags = [...$this->tags, ...array_values($event->tags)];
        });

        /** @var StaticSalesChannelRepository<ProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository($productSearches);

        return new ProductDocumentRoute(
            $productRepository,
            $documentRepository ?? new StaticEntityRepository([new ProductDocumentCollection()]),
            $mediaRepository ?? new StaticEntityRepository([]),
            new CacheTagCollector(new RequestStack(), $dispatcher),
            new ExtensionDispatcher($dispatcher),
        );
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
