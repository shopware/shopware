<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigCollection;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Product\ProductSearchBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Product\ProductSearchBuilder
 */
class ProductSearchBuilderTest extends TestCase
{
    use KernelTestBehaviour;

    private EntityRepository $productSearchConfigRepository;

    public function setUp(): void
    {
        $this->productSearchConfigRepository = $this->getContainer()->get('product_search_config.repository');
    }

    /**
     * @dataProvider providerQueries
     *
     * @param array<string>|string $query
     */
    public function testArraySearchTerm(array|string $query, string $expected): void
    {
        $criteria = new Criteria();
        $request = new Request();
        $productDefinition = new ProductDefinition();
        $request->query->set('search', $query);
        $context = Context::createDefaultContext();
        $mockSalesChannelContext = $this->createMock(SalesChannelContext::class);
        $mockSalesChannelContext->method('getContext')->willReturn($context);

        $mockProductSearchBuilder = $this->createMock(ProductSearchBuilder::class);
        $mockProductSearchBuilder->method('build')->willThrowException(new \Exception('Should not be called'));

        $mockElasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $mockElasticsearchHelper->expects(static::once())->method('allowSearch')->with($productDefinition, $context, $criteria)->willReturn(true);

        $searchBuilder = new ProductSearchBuilder(
            $mockProductSearchBuilder,
            $mockElasticsearchHelper,
            $productDefinition,
            $this->productSearchConfigRepository
        );

        $searchBuilder->build($request, $criteria, $mockSalesChannelContext);

        static::assertNotEquals('array', $criteria->getTerm());
        static::assertEquals($expected, $criteria->getTerm());
    }

    /**
     * @return iterable<string, array<int, string|array<string>>>
     */
    public static function providerQueries(): iterable
    {
        yield 'search is array' => [
            [
                'Word 1',
                'Word 2',
            ],
            'Word 1 Word 2',
        ];

        yield 'search is string' => [
            'Word 1 Word 2',
            'Word 1 Word 2',
        ];
    }

    /**
     * @return array<int, array{minSearchTerm: number, expectException: bool}>
     */
    public static function provideMinSearchTermLength(): array
    {
        return [
            [
                'minSearchTerm' => 0,
                'expectException' => false,
            ],
            [
                'minSearchTerm' => 3,
                'expectException' => true,
            ],
        ];
    }

    /**
     * @dataProvider provideMinSearchTermLength
     */
    public function testEmptyTermThrowsException(int $minSearchTerm, bool $expectException): void
    {
        $mockProductSearchBuilder = $this->createMock(ProductSearchBuilder::class);
        $mockProductSearchBuilder->method('build')->willThrowException(new \Exception('Should not be called'));

        $mockElasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $mockElasticsearchHelper->method('allowSearch')->willReturn(true);

        $productSearchConfigEntity = new ProductSearchConfigEntity();
        $productSearchConfigEntity->setId(Uuid::randomHex());
        $productSearchConfigEntity->setMinSearchLength($minSearchTerm);

        $productSearchConfigRepositoryMock = $this->createMock(EntityRepository::class);
        $productSearchConfigRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'product_search_config',
                1,
                new ProductSearchConfigCollection([$productSearchConfigEntity]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $searchBuilder = new ProductSearchBuilder(
            $mockProductSearchBuilder,
            $mockElasticsearchHelper,
            new ProductDefinition(),
            $productSearchConfigRepositoryMock
        );

        $mockSalesChannelContext = $this->createMock(SalesChannelContext::class);
        $mockSalesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $criteria = new Criteria();
        $request = new Request();

        $request->query->set('search', '');

        if ($expectException) {
            $this->expectException(MissingRequestParameterException::class);
        } else {
            $this->expectNotToPerformAssertions();
        }

        $searchBuilder->build($request, $criteria, $mockSalesChannelContext);
    }

    public function testDisabledCallsDecoration(): void
    {
        $mockProductSearchBuilder = $this->createMock(ProductSearchBuilder::class);
        $mockProductSearchBuilder->expects(static::once())->method('build');

        $mockElasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $mockElasticsearchHelper->method('allowSearch')->willReturn(false);

        $searchBuilder = new ProductSearchBuilder(
            $mockProductSearchBuilder,
            $mockElasticsearchHelper,
            new ProductDefinition(),
            $this->productSearchConfigRepository
        );

        $mockSalesChannelContext = $this->createMock(SalesChannelContext::class);
        $mockSalesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $criteria = new Criteria();
        $request = new Request();

        $request->query->set('search', 'Test');

        $searchBuilder->build($request, $criteria, $mockSalesChannelContext);
    }
}
