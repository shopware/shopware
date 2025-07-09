<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap\Provider;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Event\SalesChannelCategoryIdsFetchedEvent;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Sitemap\Provider\CategoryUrlProvider;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryUrlProvider::class)]
class CategoryUrlProviderTest extends TestCase
{
    private readonly ConfigHandler&MockObject $configHandler;

    private readonly Connection&MockObject $connection;

    private readonly CategoryDefinition&MockObject $definition;

    private readonly IteratorFactory&MockObject $iteratorFactory;

    private readonly RouterInterface&MockObject $router;

    private readonly EventDispatcher&MockObject $dispatcher;

    private readonly IdsCollection $ids;

    private int $categoryResultIncrement;

    private (QueryBuilder&MockObject)|null $queryBuilder = null;

    protected function setUp(): void
    {
        $this->configHandler = $this->createMock(ConfigHandler::class);
        $this->connection = $this->createMock(Connection::class);
        $this->definition = $this->createMock(CategoryDefinition::class);
        $this->iteratorFactory = $this->createMock(IteratorFactory::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->ids = new IdsCollection();
        $this->dispatcher = $this->createMock(EventDispatcher::class);
        $this->categoryResultIncrement = 0;
    }

    public function testGetDecorated(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->getCategoryUrlProvider()->getDecorated();
    }

    public function testGetName(): void
    {
        $name = $this->getCategoryUrlProvider()->getName();
        static::assertSame('category', $name);
    }

    public function testGetCategoryUrls(): void
    {
        $categoryResult1 = $this->createCategoryResult();
        $categoryResult2 = $this->createCategoryResult();
        $queryResult = new Result(
            new ArrayResult(
                array_keys($categoryResult1),
                [
                    array_values($categoryResult1),
                    array_values($categoryResult2),
                ]
            ),
            $this->connection
        );
        $this->initServices($queryResult);
        static::assertNotNull($this->queryBuilder);
        $context = Generator::generateSalesChannelContext();

        $event = $this->createSalesChannelCategoryIdsFetchedEvent(
            \array_column([$categoryResult1, $categoryResult2], 'id'),
            $context
        );
        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn($event);

        $provider = $this->getCategoryUrlProvider();
        $urlResult = $provider->getUrls($context, 100, 50);

        $urls = $urlResult->getUrls();
        static::assertCount(2, $urls);

        $url = array_shift($urls);
        static::assertInstanceOf(Url::class, $url);
        static::assertSame($this->ids->get('category-1'), $url->getIdentifier());
        static::assertSame('category/1/detail', $url->getLoc());

        $url = array_shift($urls);
        static::assertInstanceOf(Url::class, $url);
        static::assertSame($this->ids->get('category-2'), $url->getIdentifier());
        static::assertSame('category/2/detail', $url->getLoc());
    }

    public function testGetCategoryUrlsReturnsEmptyResult(): void
    {
        $categoryRowNames = array_keys($this->createCategoryResult());
        $queryResult = new Result(
            new ArrayResult($categoryRowNames, []),
            $this->connection
        );
        $this->initServices($queryResult);
        static::assertNotNull($this->queryBuilder);
        $context = Generator::generateSalesChannelContext();

        $provider = $this->getCategoryUrlProvider();
        $urlResult = $provider->getUrls($context, 100, 50);

        $urls = $urlResult->getUrls();
        static::assertCount(0, $urls);
    }

    public function testGetCategoryUrlsHasNoRestrictiveWhereConditionsBecauseGetExcludedCategoryIdsReturnedEmptyResult(): void
    {
        $categoryRowNames = array_keys($this->createCategoryResult());
        $queryResult = new Result(
            new ArrayResult($categoryRowNames, []),
            $this->connection
        );
        $this->initServices($queryResult, []);
        static::assertNotNull($this->queryBuilder);
        $this->configHandler->method('get')->willReturn([]);
        $context = Generator::generateSalesChannelContext();

        $provider = $this->getCategoryUrlProvider();

        $this->queryBuilder
            ->method('andWhere')
            ->willReturnCallback(function ($parameter) {
                $this->assertNotSame(
                    '`category`.id NOT IN (:categoryIds)',
                    $parameter,
                    'andWhere should never be called with category ID exclusion'
                );

                return $this->queryBuilder;
            });

        $provider->getUrls($context, 100, 50);
    }

    public function testExcludeFilteredCategories(): void
    {
        $categoryResult1 = $this->createCategoryResult();
        $categoryResult2 = $this->createCategoryResult();
        $queryResult = new Result(
            new ArrayResult(
                array_keys($categoryResult1),
                [
                    array_values($categoryResult1),
                    array_values($categoryResult2),
                ]
            ),
            $this->connection
        );
        $this->initServices($queryResult);
        static::assertNotNull($this->queryBuilder);
        $context = Generator::generateSalesChannelContext();

        $event = $this->createSalesChannelCategoryIdsFetchedEvent(
            \array_column([$categoryResult1, $categoryResult2], 'id'),
            $context,
            [$categoryResult1['id']]
        );
        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn($event);

        $provider = $this->getCategoryUrlProvider();
        $urlResult = $provider->getUrls($context, 100, 50);

        $urls = $urlResult->getUrls();
        static::assertCount(1, $urls);

        $url = array_shift($urls);
        static::assertInstanceOf(Url::class, $url);
        static::assertSame($this->ids->get('category-2'), $url->getIdentifier());
        static::assertSame('category/2/detail', $url->getLoc());
    }

    public function testReturnNextOffsetIfAllCategoriesFiltered(): void
    {
        $categoryResult1 = $this->createCategoryResult();
        $categoryResult2 = $this->createCategoryResult();
        $queryResult = new Result(
            new ArrayResult(
                array_keys($categoryResult1),
                [
                    array_values($categoryResult1),
                    array_values($categoryResult2),
                ]
            ),
            $this->connection
        );
        $this->initServices($queryResult);
        static::assertNotNull($this->queryBuilder);
        $context = Generator::generateSalesChannelContext();

        $categoryIds = \array_column([$categoryResult1, $categoryResult2], 'id');
        $event = $this->createSalesChannelCategoryIdsFetchedEvent($categoryIds, $context, $categoryIds);
        $this->dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn($event);

        $provider = $this->getCategoryUrlProvider();
        $urlResult = $provider->getUrls($context, 100, 50);

        $urls = $urlResult->getUrls();
        static::assertCount(0, $urls);
        static::assertSame(2, $urlResult->getNextOffset());
    }

    /**
     * @param array<array{resource: class-string, salesChannelId: string, identifier: string}>|null $excludedUrls
     */
    private function initServices(
        Result $categoryQueryResult,
        ?array $excludedUrls = null,
    ): void {
        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'foreign_key' => $this->ids->get('category-1'),
                'seo_path_info' => 'category/1/detail',
            ],
        ]);

        $this->router->method('generate')->willReturn('category/2/detail');

        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->queryBuilder->method('executeQuery')->willReturn($categoryQueryResult);

        $query = $this->createMock(IterableQuery::class);
        $query->method('getQuery')->willReturn($this->queryBuilder);

        $this->iteratorFactory->method('createIterator')->willReturn($query);
        $this->configHandler->method('get')
            ->willReturn($excludedUrls ?? $this->getDefaultExcludedUrls());
    }

    private function getCategoryUrlProvider(): CategoryUrlProvider
    {
        return new CategoryUrlProvider(
            $this->configHandler,
            $this->connection,
            $this->definition,
            $this->iteratorFactory,
            $this->router,
            $this->dispatcher
        );
    }

    /**
     * @return array{increment: int, id: string, created_at: string, updated_at: ?string}
     */
    private function createCategoryResult(): array
    {
        return [
            'increment' => ++$this->categoryResultIncrement,
            'id' => $this->ids->get('category-' . $this->categoryResultIncrement),
            'created_at' => '2021-01-01 00:00:00',
            'updated_at' => null,
        ];
    }

    /**
     * @return array<array{resource: class-string, salesChannelId: string, identifier: string}>
     */
    private function getDefaultExcludedUrls(): array
    {
        return [
            [
                'resource' => CategoryEntity::class,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'identifier' => $this->ids->get('category-1'),
            ],
            [
                'resource' => CategoryEntity::class,
                'salesChannelId' => Uuid::randomHex(),
                'identifier' => $this->ids->get('category-2'),
            ],
            [
                'resource' => ProductEntity::class,
                'salesChannelId' => Uuid::randomHex(),
                'identifier' => $this->ids->get('product-3'),
            ],
        ];
    }

    /**
     * @param list<string> $categoryIds
     * @param list<string> $filterIds
     */
    private function createSalesChannelCategoryIdsFetchedEvent(
        array $categoryIds,
        SalesChannelContext $context,
        array $filterIds = []
    ): SalesChannelCategoryIdsFetchedEvent {
        $categoryIdsFetchedEvent = new SalesChannelCategoryIdsFetchedEvent(
            $categoryIds,
            $context
        );
        \array_map(fn (string $categoryId) => $categoryIdsFetchedEvent->filterId($categoryId), $filterIds);

        return $categoryIdsFetchedEvent;
    }
}
