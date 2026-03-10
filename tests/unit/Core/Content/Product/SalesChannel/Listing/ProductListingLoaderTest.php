<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ProductListingLoader::class)]
class ProductListingLoaderTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    private string $parentId;

    private string $variantAId;

    private string $variantBId;

    private string $variantCId;

    protected function setUp(): void
    {
        $this->salesChannelContext = Generator::generateSalesChannelContext();
        $this->parentId = Uuid::randomHex();
        $this->variantAId = Uuid::randomHex();
        $this->variantBId = Uuid::randomHex();
        $this->variantCId = Uuid::randomHex();
    }

    public function testSwapBestVariantReturnsHighestScoredVariant(): void
    {
        $criteria = $this->createSearchCriteria('test product');
        $context = $this->salesChannelContext->getContext();

        $groupedResult = new IdSearchResult(
            1,
            [$this->variantAId => ['primaryKey' => $this->variantAId, 'data' => ['_score' => 80]]],
            new Criteria(),
            $context
        );

        $scoredResult = new IdSearchResult(
            3,
            [
                $this->variantBId => ['primaryKey' => $this->variantBId, 'data' => ['_score' => 100]],
                $this->variantAId => ['primaryKey' => $this->variantAId, 'data' => ['_score' => 80]],
                $this->variantCId => ['primaryKey' => $this->variantCId, 'data' => ['_score' => 60]],
            ],
            new Criteria(),
            $context
        );

        $loader = $this->createLoader(
            searchIdsResults: [$groupedResult, $scoredResult],
            connection: $this->createSiblingConnection([
                ['id' => $this->variantAId, 'parentId' => $this->parentId],
                ['id' => $this->variantBId, 'parentId' => $this->parentId],
                ['id' => $this->variantCId, 'parentId' => $this->parentId],
            ]),
            findBestVariant: true,
        );

        $result = $loader->load($criteria, $this->salesChannelContext);

        static::assertContains($this->variantBId, $result->getIds());
        static::assertNotContains($this->variantAId, $result->getIds());
        static::assertNotContains($this->variantCId, $result->getIds());
    }

    public function testSwapBestVariantWithMultipleFamilies(): void
    {
        $parent2Id = Uuid::randomHex();
        $variant2AId = Uuid::randomHex();
        $variant2BId = Uuid::randomHex();

        $criteria = $this->createSearchCriteria('test');
        $context = $this->salesChannelContext->getContext();

        $groupedResult = new IdSearchResult(
            2,
            [
                $this->variantAId => ['primaryKey' => $this->variantAId, 'data' => ['_score' => 80]],
                $variant2AId => ['primaryKey' => $variant2AId, 'data' => ['_score' => 70]],
            ],
            new Criteria(),
            $context
        );

        $scoredResult = new IdSearchResult(
            4,
            [
                $this->variantBId => ['primaryKey' => $this->variantBId, 'data' => ['_score' => 100]],
                $variant2BId => ['primaryKey' => $variant2BId, 'data' => ['_score' => 90]],
                $this->variantAId => ['primaryKey' => $this->variantAId, 'data' => ['_score' => 80]],
                $variant2AId => ['primaryKey' => $variant2AId, 'data' => ['_score' => 70]],
            ],
            new Criteria(),
            $context
        );

        $loader = $this->createLoader(
            searchIdsResults: [$groupedResult, $scoredResult],
            connection: $this->createSiblingConnection([
                ['id' => $this->variantAId, 'parentId' => $this->parentId],
                ['id' => $this->variantBId, 'parentId' => $this->parentId],
                ['id' => $variant2AId, 'parentId' => $parent2Id],
                ['id' => $variant2BId, 'parentId' => $parent2Id],
            ]),
            findBestVariant: true,
        );

        $result = $loader->load($criteria, $this->salesChannelContext);

        static::assertContains($this->variantBId, $result->getIds());
        static::assertContains($variant2BId, $result->getIds());
        static::assertNotContains($this->variantAId, $result->getIds());
        static::assertNotContains($variant2AId, $result->getIds());
    }

    public function testSwapBestVariantPreservesOriginalTotal(): void
    {
        $criteria = $this->createSearchCriteria('test');
        $context = $this->salesChannelContext->getContext();

        $groupedResult = new IdSearchResult(
            42,
            [$this->variantAId => ['primaryKey' => $this->variantAId, 'data' => ['_score' => 80]]],
            new Criteria(),
            $context
        );

        $scoredResult = new IdSearchResult(
            2,
            [
                $this->variantBId => ['primaryKey' => $this->variantBId, 'data' => ['_score' => 100]],
                $this->variantAId => ['primaryKey' => $this->variantAId, 'data' => ['_score' => 80]],
            ],
            new Criteria(),
            $context
        );

        $loader = $this->createLoader(
            searchIdsResults: [$groupedResult, $scoredResult],
            connection: $this->createSiblingConnection([
                ['id' => $this->variantAId, 'parentId' => $this->parentId],
                ['id' => $this->variantBId, 'parentId' => $this->parentId],
            ]),
            findBestVariant: true,
        );

        $result = $loader->load($criteria, $this->salesChannelContext);

        static::assertSame(42, $result->getTotal());
        static::assertContains($this->variantBId, $result->getIds());
    }

    public function testSwapBestVariantIsSkippedForElasticsearchResults(): void
    {
        $criteria = $this->createSearchCriteria('test');
        $context = $this->salesChannelContext->getContext();

        $esResult = new IdSearchResult(
            1,
            [$this->variantAId => ['primaryKey' => $this->variantAId, 'data' => ['_score' => 80]]],
            new Criteria(),
            $context
        );
        $esResult->addState('loaded-by-elastic');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');

        $loader = $this->createLoader(
            searchIdsResults: [$esResult],
            connection: $connection,
            findBestVariant: true,
        );

        $result = $loader->load($criteria, $this->salesChannelContext);

        static::assertContains($this->variantAId, $result->getIds());
    }

    public function testSwapBestVariantIsSkippedWhenNoSiblingsFound(): void
    {
        $criteria = $this->createSearchCriteria('test');
        $context = $this->salesChannelContext->getContext();

        $groupedResult = new IdSearchResult(
            1,
            [$this->variantAId => ['primaryKey' => $this->variantAId, 'data' => ['_score' => 80]]],
            new Criteria(),
            $context
        );

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn([]);

        $loader = $this->createLoader(
            searchIdsResults: [$groupedResult],
            connection: $connection,
            findBestVariant: true,
        );

        $result = $loader->load($criteria, $this->salesChannelContext);

        static::assertContains($this->variantAId, $result->getIds());
    }

    public function testSwapBestVariantSkipsStandaloneProducts(): void
    {
        $standaloneId = Uuid::randomHex();

        $criteria = $this->createSearchCriteria('test');
        $context = $this->salesChannelContext->getContext();

        $groupedResult = new IdSearchResult(
            2,
            [
                $standaloneId => ['primaryKey' => $standaloneId, 'data' => ['_score' => 90]],
                $this->variantAId => ['primaryKey' => $this->variantAId, 'data' => ['_score' => 80]],
            ],
            new Criteria(),
            $context
        );

        $scoredResult = new IdSearchResult(
            2,
            [
                $this->variantBId => ['primaryKey' => $this->variantBId, 'data' => ['_score' => 100]],
                $this->variantAId => ['primaryKey' => $this->variantAId, 'data' => ['_score' => 80]],
            ],
            new Criteria(),
            $context
        );

        $loader = $this->createLoader(
            searchIdsResults: [$groupedResult, $scoredResult],
            connection: $this->createSiblingConnection([
                ['id' => $this->variantAId, 'parentId' => $this->parentId],
                ['id' => $this->variantBId, 'parentId' => $this->parentId],
            ]),
            findBestVariant: true,
        );

        $result = $loader->load($criteria, $this->salesChannelContext);

        static::assertContains($standaloneId, $result->getIds(), 'Standalone product should remain unchanged');
        static::assertContains($this->variantBId, $result->getIds(), 'Best variant should be swapped in');
        static::assertNotContains($this->variantAId, $result->getIds(), 'Lower-scored variant should be swapped out');
    }

    public function testSwapBestVariantKeepsOriginalWhenAlreadyBest(): void
    {
        $criteria = $this->createSearchCriteria('test');
        $context = $this->salesChannelContext->getContext();

        $groupedResult = new IdSearchResult(
            1,
            [$this->variantAId => ['primaryKey' => $this->variantAId, 'data' => ['_score' => 100]]],
            new Criteria(),
            $context
        );

        $scoredResult = new IdSearchResult(
            2,
            [
                $this->variantAId => ['primaryKey' => $this->variantAId, 'data' => ['_score' => 100]],
                $this->variantBId => ['primaryKey' => $this->variantBId, 'data' => ['_score' => 60]],
            ],
            new Criteria(),
            $context
        );

        $loader = $this->createLoader(
            searchIdsResults: [$groupedResult, $scoredResult],
            connection: $this->createSiblingConnection([
                ['id' => $this->variantAId, 'parentId' => $this->parentId],
                ['id' => $this->variantBId, 'parentId' => $this->parentId],
            ]),
            findBestVariant: true,
        );

        $result = $loader->load($criteria, $this->salesChannelContext);

        static::assertContains($this->variantAId, $result->getIds());
        static::assertNotContains($this->variantBId, $result->getIds());
    }

    private function createSearchCriteria(string $term): Criteria
    {
        $criteria = new Criteria();
        $criteria->addState(ResolvedCriteriaProductSearchRoute::STATE);
        $criteria->setTerm($term);

        return $criteria;
    }

    /**
     * @param list<IdSearchResult> $searchIdsResults
     */
    private function createLoader(
        array $searchIdsResults,
        Connection&MockObject $connection,
        bool $findBestVariant = false,
    ): ProductListingLoader {
        /** @var SalesChannelRepository<ProductCollection>&MockObject $productRepository */
        $productRepository = $this->createMock(SalesChannelRepository::class);

        $productRepository->method('searchIds')
            ->willReturnOnConsecutiveCalls(...$searchIdsResults);

        $productRepository->method('aggregate')
            ->willReturn(new AggregationResultCollection());

        $productRepository->method('search')
            ->willReturnCallback(function (Criteria $criteria, SalesChannelContext $ctx): EntitySearchResult {
                $entities = [];
                foreach ($criteria->getIds() as $id) {
                    $entity = new SalesChannelProductEntity();
                    $entity->setId($id);
                    $entity->setUniqueIdentifier($id);
                    $entities[] = $entity;
                }
                $collection = new ProductCollection($entities);

                return new EntitySearchResult(
                    'product',
                    $collection->count(),
                    $collection,
                    null,
                    $criteria,
                    $ctx->getContext()
                );
            });

        $salesChannelId = $this->salesChannelContext->getSalesChannelId();

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('getBool')
            ->willReturnMap([
                ['core.listing.findBestVariant', $salesChannelId, $findBestVariant],
                ['core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelId, false],
            ]);

        $dispatcher = new EventDispatcher();

        return new ProductListingLoader(
            $productRepository,
            $systemConfig,
            $connection,
            $dispatcher,
            $this->createMock(AbstractProductCloseoutFilterFactory::class),
            new ExtensionDispatcher($dispatcher),
        );
    }

    /**
     * @param list<array{id: string, parentId: string}> $siblings
     */
    private function createSiblingConnection(array $siblings): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);

        $connection->method('fetchAllKeyValue')
            ->willReturn(array_column($siblings, 'parentId', 'id'));

        return $connection;
    }
}
