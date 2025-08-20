<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Shopware\Elasticsearch\Product\ElasticsearchOptimizeSwitch;
use Shopware\Elasticsearch\Product\ProductCriteriaParser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ProductCriteriaParser::class)]
class ProductCriteriaParserTest extends TestCase
{
    private EntityDefinitionQueryHelper $helper;

    private CustomFieldService&MockObject $customFieldService;

    private CriteriaParser&MockObject $decoratedParser;

    private EntityDefinition $productDefinition;

    private EntityDefinition $categoryDefinition;

    private Context $context;

    protected function setUp(): void
    {
        $registry = $this->getRegistry();

        $this->helper = new EntityDefinitionQueryHelper();
        $this->customFieldService = $this->createMock(CustomFieldService::class);
        $this->decoratedParser = $this->createMock(CriteriaParser::class);
        $this->productDefinition = $registry->getByEntityName(ProductDefinition::ENTITY_NAME);
        $this->categoryDefinition = $registry->getByEntityName(CategoryDefinition::ENTITY_NAME);
        $this->context = new Context(new SystemSource());
    }

    public function testParseFilterWithNonProductDefinition(): void
    {
        $storage = new ArrayKeyValueStorage();
        $parser = new ProductCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        $filter = new EqualsFilter('name', 'test');

        $this->decoratedParser
            ->expects($this->never())
            ->method('parseFilter');

        $result = $parser->parseFilter($filter, $this->categoryDefinition, 'root', $this->context);

        static::assertInstanceOf(TermQuery::class, $result);
        static::assertSame([
            'term' => [
                'name.2fbb5fe2e29a4d70aa5854ce7ce3e20b' => 'test',
            ],
        ], $result->toArray());
    }

    public function testParseFilterShouldCallsParent(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $storage = new ArrayKeyValueStorage();
        $parser = new ProductCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        $filter = new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('active', true)]);
        $expectedBuilder = $this->createMock(BuilderInterface::class);

        $this->decoratedParser
            ->expects($this->once())
            ->method('parseFilter')
            ->with($filter, $this->productDefinition, 'root', $this->context)
            ->willReturn($expectedBuilder);

        $result = $parser->parseFilter($filter, $this->productDefinition, 'root', $this->context);

        static::assertSame($expectedBuilder, $result);
    }

    public function testParseProductAvailableFilterWithOptimizationDisabled(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $storage = new ArrayKeyValueStorage();
        $parser = new ProductCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        $salesChannelId = Uuid::randomHex();
        $filter = new ProductAvailableFilter($salesChannelId, 30);
        $expectedBuilder = $this->createMock(BuilderInterface::class);

        $this->decoratedParser
            ->expects($this->once())
            ->method('parseFilter')
            ->with($filter, $this->productDefinition, 'root', $this->context)
            ->willReturn($expectedBuilder);

        $result = $parser->parseFilter($filter, $this->productDefinition, 'root', $this->context);

        static::assertSame($expectedBuilder->toArray(), $result->toArray());
    }

    public function testParseProductAvailableFilterWithOptimizationEnabled(): void
    {
        $storage = new ArrayKeyValueStorage([
            ElasticsearchOptimizeSwitch::FLAG => true,
        ]);
        $parser = new ProductCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        $salesChannelId = Uuid::randomHex();
        $visibility = 30;
        $filter = new ProductAvailableFilter($salesChannelId, $visibility);

        $result = $parser->parseFilter($filter, $this->productDefinition, 'root', $this->context);

        static::assertInstanceOf(BoolQuery::class, $result);

        $queryArray = $result->toArray();

        static::assertArrayHasKey('bool', $queryArray);
        static::assertArrayHasKey('must', $queryArray['bool']);
        static::assertCount(2, $queryArray['bool']['must']);

        $activeQuery = $queryArray['bool']['must'][0];
        static::assertArrayHasKey('term', $activeQuery);
        static::assertArrayHasKey('active', $activeQuery['term']);
        static::assertTrue($activeQuery['term']['active']);

        $visibilityQuery = $queryArray['bool']['must'][1];
        static::assertArrayHasKey('range', $visibilityQuery);
        static::assertArrayHasKey('visibility_' . $salesChannelId, $visibilityQuery['range']);
        static::assertArrayHasKey('gte', $visibilityQuery['range']['visibility_' . $salesChannelId]);
        static::assertSame($visibility, $visibilityQuery['range']['visibility_' . $salesChannelId]['gte']);
    }

    public function testParseCategoriesRoIdEqualsFilter(): void
    {
        $storage = new ArrayKeyValueStorage();
        $parser = new ProductCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        $categoryId = Uuid::randomHex();
        $filter = new EqualsFilter('categoriesRo.id', $categoryId);

        $result = $parser->parseFilter($filter, $this->productDefinition, 'root', $this->context);

        static::assertInstanceOf(TermQuery::class, $result);

        $queryArray = $result->toArray();
        static::assertArrayHasKey('term', $queryArray);
        static::assertArrayHasKey('categoryTree', $queryArray['term']);
        static::assertSame($categoryId, $queryArray['term']['categoryTree']);
    }

    public function testParseCategoriesRoIdEqualsFilterWithNullValue(): void
    {
        $storage = new ArrayKeyValueStorage([
            ElasticsearchOptimizeSwitch::FLAG => true,
        ]);

        $parser = new ProductCriteriaParser(
            $this->helper,
            $this->customFieldService,
            $storage,
            $this->decoratedParser
        );

        $filter = new EqualsFilter('categoriesRo.id', null);

        $this->decoratedParser
            ->expects($this->never())
            ->method('parseFilter');

        $result = $parser->parseFilter($filter, $this->productDefinition, 'root', $this->context);

        static::assertInstanceOf(BoolQuery::class, $result);
        $queryArray = $result->toArray();
        static::assertArrayHasKey('bool', $queryArray);
        static::assertArrayHasKey('must_not', $queryArray['bool']);
        static::assertIsArray($queryArray['bool']['must_not']);
        static::assertNotEmpty($queryArray['bool']['must_not'][0]);
        static::assertSame([
            'exists' => [
                'field' => 'categoryTree',
            ],
        ], $queryArray['bool']['must_not'][0]);
    }

    private function getRegistry(): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class,
                CategoryDefinition::class,
                CategoryTranslationDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }
}
