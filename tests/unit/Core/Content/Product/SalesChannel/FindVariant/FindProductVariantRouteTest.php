<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\FindVariant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Exception\VariantNotFoundException;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilter;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(FindProductVariantRoute::class)]
class FindProductVariantRouteTest extends TestCase
{
    /**
     * @var Stub&SalesChannelRepository<ProductCollection>
     */
    private Stub&SalesChannelRepository $productRepositoryMock;

    private CacheTagCollector&Stub $cacheTagCollector;

    private Stub&SystemConfigService $systemConfigService;

    private FindProductVariantRoute $route;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->productRepositoryMock = static::createStub(SalesChannelRepository::class);
        $this->cacheTagCollector = static::createStub(CacheTagCollector::class);
        $this->systemConfigService = static::createStub(SystemConfigService::class);
        $this->route = $this->createRoute();
        $this->ids = new IdsCollection();
    }

    public function testNoDecoration(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(FindProductVariantRoute::class));

        $this->route->getDecorated();
    }

    public function testLoad(): void
    {
        $options = [
            $this->ids->get('group1') => $this->ids->get('option1'),
            $this->ids->get('group2') => $this->ids->get('option2'),
        ];

        $request = new Request(
            [
                'switched' => $this->ids->get('element'),
                'options' => $options,
            ]
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $this->ids->get('productId')));
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option1')));
        $criteria->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option2')));
        $criteria->addFilter(new ProductCloseoutFilter());

        $context = Context::createDefaultContext();

        $found1Id = $this->ids->get('found1');
        $found2Id = $this->ids->get('found2');
        $this->productRepositoryMock->method('searchIds')
            ->willReturn(
                new IdSearchResult(
                    2,
                    [
                        $found1Id => [
                            'primaryKey' => $found1Id,
                            'data' => [],
                        ],
                        $found2Id => [
                            'primaryKey' => $found2Id,
                            'data' => [],
                        ],
                    ],
                    $criteria,
                    $context
                )
            );

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())
            ->method('addTag')
            ->with(EntityCacheKeyGenerator::buildProductTag($this->ids->get('productId')));

        $this->systemConfigService->method('getBool')->willReturn(true);

        $response = $this->createRoute($cacheTagCollector)->load($this->ids->get('productId'), $request, static::createStub(SalesChannelContext::class));

        static::assertSame($found1Id, $response->getFoundCombination()->getVariantId());
        static::assertSame($options, $response->getFoundCombination()->getOptions());
    }

    public function testLoadFirstVariantNotFound(): void
    {
        $options = [
            $this->ids->get('group1') => $this->ids->get('option1'),
            $this->ids->get('group2') => $this->ids->get('option2'),
        ];

        $request = new Request(
            [
                'switched' => $this->ids->get('element'),
                'options' => $options,
            ]
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $this->ids->get('productId')));
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option1')));
        $criteria->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option2')));

        $criteria2 = new Criteria();
        $criteria2->addFilter(new EqualsFilter('product.parentId', $this->ids->get('productId')));
        $criteria2->setLimit(1);
        $criteria2->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option2')));

        $context = Context::createDefaultContext();

        $found1Id = $this->ids->get('found1');
        $this->productRepositoryMock->method('searchIds')
            ->willReturnOnConsecutiveCalls(
                new IdSearchResult(
                    0,
                    [
                    ],
                    $criteria,
                    $context
                ),
                new IdSearchResult(
                    1,
                    [
                        $found1Id => [
                            'primaryKey' => $found1Id,
                            'data' => [],
                        ],
                    ],
                    $criteria2,
                    $context
                ),
            );

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())
            ->method('addTag')
            ->with(EntityCacheKeyGenerator::buildProductTag($this->ids->get('productId')));

        $response = $this->createRoute($cacheTagCollector)->load($this->ids->get('productId'), $request, static::createStub(SalesChannelContext::class));

        static::assertSame($found1Id, $response->getFoundCombination()->getVariantId());
    }

    public function testLoadNoVariantFound(): void
    {
        $options = [
            $this->ids->get('group1') => $this->ids->get('option1'),
            $this->ids->get('group2') => $this->ids->get('option2'),
        ];

        $request = new Request(
            [
                'switched' => $this->ids->get('element'),
                'options' => $options,
            ]
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $this->ids->get('productId')));
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option1')));
        $criteria->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option2')));

        $criteria2 = new Criteria();
        $criteria2->addFilter(new EqualsFilter('product.parentId', $this->ids->get('productId')));
        $criteria2->setLimit(1);
        $criteria2->addFilter(new EqualsFilter('product.optionIds', $this->ids->get('option2')));

        $context = Context::createDefaultContext();

        $this->productRepositoryMock->method('searchIds')
            ->willReturnOnConsecutiveCalls(
                new IdSearchResult(
                    0,
                    [
                    ],
                    $criteria,
                    $context
                ),
                new IdSearchResult(
                    0,
                    [
                    ],
                    $criteria2,
                    $context
                ),
            );

        $this->expectExceptionObject(ProductException::variantNotFound(
            $this->ids->get('productId'),
            [$this->ids->get('group2') => $this->ids->get('option2')]
        ));

        try {
            $this->route->load($this->ids->get('productId'), $request, static::createStub(SalesChannelContext::class));
        } catch (VariantNotFoundException $e) {
            static::assertSame('CONTENT__PRODUCT_VARIANT_NOT_FOUND', $e->getErrorCode());

            throw $e;
        }
    }

    public function testLoadWithInvalidOptions(): void
    {
        $options = ['optionId1', []];

        $request = new Request(
            [
                'switched' => $this->ids->get('element'),
                'options' => $options,
            ]
        );
        $this->expectExceptionObject(ProductException::invalidOptionsParameter());

        $this->route->load($this->ids->get('productId'), $request, static::createStub(SalesChannelContext::class));
    }

    private function createRoute(?CacheTagCollector $cacheTagCollector = null): FindProductVariantRoute
    {
        return new FindProductVariantRoute(
            $this->productRepositoryMock,
            $cacheTagCollector ?? $this->cacheTagCollector,
            $this->systemConfigService,
            new ProductCloseoutFilterFactory(),
        );
    }
}
