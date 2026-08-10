<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Repository;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilder;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
class ProductSearchScoringTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('product.repository');
    }

    public function testScoringExtensionExists(): void
    {
        $context = Context::createDefaultContext();
        $pattern = new SearchPattern(new SearchTerm('test'));
        $builder = new EntityScoreQueryBuilder();
        $queries = $builder->buildScoreQueries(
            $pattern,
            static::getContainer()->get(ProductDefinition::class),
            static::getContainer()->get(ProductDefinition::class)->getEntityName(),
            $context
        );

        $criteria = new Criteria();
        $criteria->addQuery(...$queries);

        $this->repository->create([
            ['id' => Uuid::randomHex(), 'productNumber' => Uuid::randomHex(), 'stock' => 10, 'name' => 'product 1 test', 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test'], 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]]],
            ['id' => Uuid::randomHex(), 'productNumber' => Uuid::randomHex(), 'stock' => 10, 'name' => 'product 2 test', 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test'], 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]]],
        ], $context);

        foreach ($this->repository->search($criteria, $context)->getEntities() as $entity) {
            static::assertArrayHasKey('search', $entity->getExtensions());
            $extension = $entity->getExtension('search');

            static::assertInstanceOf(ArrayEntity::class, $extension);
            static::assertArrayHasKey('_score', $extension);
            static::assertGreaterThan(0, (float) $extension->get('_score'));
        }
    }

    public function testMultipleMatchingKeywordsHaveHigherScore(): void
    {
        $context = Context::createDefaultContext();
        $mockSalesChannelContext = $this->createMock(SalesChannelContext::class);
        $mockSalesChannelContext->method('getContext')->willReturn($context);
        $mockSalesChannelContext->method('getLanguageId')->willReturn($context->getLanguageId());

        $productMultipleMatchId = Uuid::randomHex();
        $productFirstWordMatchId = Uuid::randomHex();
        $productSecondWordMatchId = Uuid::randomHex();
        $productNoWordMatchId = Uuid::randomHex();

        $this->repository->create([
            [
                'id' => $productMultipleMatchId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Saphir Ring Gold',
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            ],
            [
                'id' => $productFirstWordMatchId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Saphir Necklace Gold',
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            ],
            [
                'id' => $productSecondWordMatchId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Diamond Ring Silver',
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            ],
            [
                'id' => $productNoWordMatchId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Diamond Necklace Silver',
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            ],
        ], $context);

        $criteria = new Criteria();

        $pattern = new SearchPattern(new SearchTerm('ring saphir', 1.0));
        $pattern->addTerm(new SearchTerm('ring', 1.1));
        $pattern->addTerm(new SearchTerm('saphir', 1.1));
        $pattern->addTerm(new SearchTerm('saphir ring gold', 0.9));
        $pattern->addTerm(new SearchTerm('saphir necklace gold', 0.5));
        $pattern->setTokenTerms([
            ['ring'],
            ['saphir', 'saphir necklace gold', 'saphir ring gold'],
        ]);

        $termInterpreter = $this->createMock(ProductSearchTermInterpreterInterface::class);
        $termInterpreter->expects($this->once())
            ->method('interpret')
            ->with('ring saphir', static::isInstanceOf(Context::class))
            ->willReturn($pattern);
        $logger = $this->createMock(LoggerInterface::class);
        $searchBuilder = new ProductSearchBuilder(
            $termInterpreter,
            $logger,
            20
        );

        $criteria = new Criteria();
        $request = new Request();

        $request->query->set('search', 'ring saphir');

        $searchBuilder->build($request, $criteria, $mockSalesChannelContext);

        $result = $this->repository->search($criteria, $context);

        $productResults = [];

        foreach ($result->getEntities() as $entity) {
            static::assertArrayHasKey('search', $entity->getExtensions());
            $extension = $entity->getExtension('search');

            static::assertInstanceOf(ArrayEntity::class, $extension);
            static::assertArrayHasKey('_score', $extension);

            $productResults[$entity->getId()] = [
                'name' => $entity->getName(),
                'score' => (float) $extension->get('_score'),
            ];
        }

        static::assertArrayHasKey($productMultipleMatchId, $productResults);
        static::assertArrayHasKey($productFirstWordMatchId, $productResults);
        static::assertArrayHasKey($productSecondWordMatchId, $productResults);
        static::assertArrayNotHasKey($productNoWordMatchId, $productResults);
        static::assertSame(2170.0, round($productResults[$productMultipleMatchId]['score']));
        static::assertSame(1120.0, round($productResults[$productFirstWordMatchId]['score']));
        static::assertSame(770.0, round($productResults[$productSecondWordMatchId]['score']));
    }

    public function testGroupedVariantsScoreLikeAComparableSingleProduct(): void
    {
        $context = Context::createDefaultContext();

        $singleProductId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $optionIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $this->repository->create([
            [
                'id' => $singleProductId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Arbeitshandschuhe',
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            ],
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Arbeitshandschuhe',
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'children' => array_map(static fn (string $optionId) => [
                    'id' => Uuid::randomHex(),
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 10,
                    'options' => [
                        ['id' => $optionId, 'name' => $optionId, 'group' => ['id' => $optionIds[0], 'name' => 'color']],
                    ],
                ], $optionIds),
            ],
        ], $context);

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new EqualsFilter('searchKeywords.keyword', 'arbeitshandschuhe'), 500, 'searchKeywords.ranking'));
        // the same grouping the product listing applies to display variants as one product
        $criteria->addGroupField(new FieldGrouping('displayGroup'));
        $criteria->addFilter(new NotEqualsFilter('displayGroup', null));

        $result = $this->repository->searchIds($criteria, $context);

        $scores = [];
        foreach ($result->getIds() as $id) {
            static::assertIsString($id);
            $scores[] = round((float) $result->getDataFieldOfId($id, '_score'));
        }

        // one score for the single product, one for the group of three variants
        static::assertCount(2, $scores);
        static::assertSame($scores[0], $scores[1], 'The variant group must not be scored higher than a comparable single product');
    }
}
