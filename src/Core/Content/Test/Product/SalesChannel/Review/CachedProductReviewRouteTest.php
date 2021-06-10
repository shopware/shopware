<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\Review;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\SalesChannel\Review\CachedProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRouteResponse;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheTracer;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group cache
 * @group store-api
 */
class CachedProductReviewRouteTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
    }

    /**
     * @afterClass
     */
    public function cleanup(): void
    {
        $this->getContainer()->get('cache.object')
            ->invalidateTags([CachedProductReviewRoute::ALL_TAG]);
    }

    /**
     * @dataProvider criteriaProvider
     */
    public function testCriteria(Criteria $criteria): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $response = new ProductReviewRouteResponse(
            new EntitySearchResult('product_review', 0, new ProductReviewCollection(), null, $criteria, $context->getContext())
        );

        $core = $this->createMock(ProductReviewRoute::class);
        $core->expects(static::exactly(2))
            ->method('load')
            ->willReturn($response);

        $route = new CachedProductReviewRoute(
            $core,
            new TagAwareAdapter(new ArrayAdapter(100)),
            $this->getContainer()->get(EntityCacheKeyGenerator::class),
            $this->getContainer()->get(CacheTracer::class),
            $this->getContainer()->get('event_dispatcher'),
            [],
            $this->getContainer()->get('logger')
        );

        $ids = new IdsCollection();
        $route->load($ids->get('product'), new Request(), $context, $criteria);

        $route->load($ids->get('product'), new Request(), $context, $criteria);

        $criteria->setLimit(200);

        // check that provided criteria has other key
        $route->load($ids->get('product'), new Request(), $context, $criteria);
    }

    public function criteriaProvider(): \Generator
    {
        yield 'Paginated criteria' => [(new Criteria())->setOffset(1)->setLimit(20)];
        yield 'Filtered criteria' => [(new Criteria())->addFilter(new EqualsFilter('active', true))];
        yield 'Post filtered criteria' => [(new Criteria())->addPostFilter(new EqualsFilter('active', true))];
        yield 'Aggregation criteria' => [(new Criteria())->addAggregation(new StatsAggregation('name', 'name'))];
        yield 'Query criteria' => [(new Criteria())->addQuery(new ScoreQuery(new EqualsFilter('active', true), 200))];
        yield 'Term criteria' => [(new Criteria())->setTerm('test')];
        yield 'Sorted criteria' => [(new Criteria())->addSorting(new FieldSorting('active'))];
    }

    /**
     * @dataProvider invalidationProvider
     */
    public function testInvalidation(IdsCollection $ids, \Closure $before, \Closure $after, int $calls): void
    {
        $this->getContainer()->get('cache.object')
            ->invalidateTags([CachedProductReviewRoute::ALL_TAG]);

        $products = [
            (new ProductBuilder($ids, 'product'))
                ->price(100)
                ->visibility()
                ->review('Super', 'Amazing product!!!!')
                ->build(),

            (new ProductBuilder($ids, 'other-product'))
                ->price(100)
                ->visibility()
                ->review('Super', 'Amazing product!!!!')
                ->build(),
        ];

        $this->getContainer()->get('product.repository')
            ->upsert($products, $ids->getContext());

        $productId = $ids->get('product');

        $route = $this->getContainer()->get(ProductReviewRoute::class);

        static::assertInstanceOf(CachedProductReviewRoute::class, $route);

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();

        $listener->expects(static::exactly($calls))->method('__invoke');
        $dispatcher->addListener('product_review.loaded', $listener);

        $before($ids);

        $route->load($productId, new Request(), $this->context, new Criteria());
        $route->load($productId, new Request(), $this->context, new Criteria());

        $after($ids);

        $route->load($productId, new Request(), $this->context, new Criteria());
        $route->load($productId, new Request(), $this->context, new Criteria());
    }

    public function invalidationProvider()
    {
        $ids = new IdsCollection();

        yield 'Cache invalidated if review created' => [
            $ids,
            function (): void {
            },
            function (IdsCollection $ids): void {
                $data = self::review($ids->get('review'), $ids->get('product'), 'Title', 'Content');

                $this->getContainer()->get('product_review.repository')->create([$data], $ids->getContext());
            },
            2,
        ];

        yield 'Cache invalidated if review updated' => [
            $ids,
            function (IdsCollection $ids): void {
                $data = self::review($ids->get('review-update'), $ids->get('product'), 'Title', 'Content');

                $this->getContainer()->get('product_review.repository')->create([$data], $ids->getContext());
            },
            function (IdsCollection $ids): void {
                $data = ['id' => $ids->get('review-update'), 'title' => 'updated'];

                $this->getContainer()->get('product_review.repository')->update([$data], $ids->getContext());
            },
            2,
        ];

        yield 'Cache invalidated if review deleted' => [
            $ids,
            function (IdsCollection $ids): void {
                $data = self::review($ids->get('to-delete'), $ids->get('product'), 'Title', 'Content');

                $this->getContainer()->get('product_review.repository')->create([$data], $ids->getContext());
            },
            function (IdsCollection $ids): void {
                $data = ['id' => $ids->get('to-delete')];

                $this->getContainer()->get('product_review.repository')->delete([$data], $ids->getContext());
            },
            2,
        ];

        yield 'Cache not invalidated if other review created' => [
            $ids,
            function (): void {
            },
            function (IdsCollection $ids): void {
                $data = self::review($ids->get('other-review'), $ids->get('other-product'), 'Title', 'Content');

                $this->getContainer()->get('product_review.repository')->create([$data], $ids->getContext());
            },
            1,
        ];
    }

    private static function review(string $id, string $productId, string $title, string $content, float $points = 3, string $salesChannelId = Defaults::SALES_CHANNEL, string $languageId = Defaults::LANGUAGE_SYSTEM): array
    {
        return [
            'id' => $id,
            'productId' => $productId,
            'title' => $title,
            'content' => $content,
            'points' => $points,
            'languageId' => $languageId,
            'salesChannelId' => $salesChannelId,
        ];
    }
}
