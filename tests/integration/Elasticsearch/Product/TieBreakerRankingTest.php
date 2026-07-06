<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Product\ElasticsearchOptimizeSwitch;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests that tie_breaker in dis_max queries produces correct ranking by rewarding
 * documents with broader matching evidence (multiple clause types, multiple languages).
 *
 * @internal
 */
#[Package('inventory')]
class TieBreakerRankingTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use ElasticsearchTestTestBehaviour;
    use FilesystemBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use SessionTestBehaviour;

    private static IdsCollection $ids;

    /**
     * @param list<array<string, mixed>> $products
     * @param list<string> $searchFields
     * @param array<string, int> $fieldScores
     * @param list<string> $expectedOrder first element = highest ranked
     */
    #[DataProvider('tieBreakerRankingProvider')]
    #[TestDox('$_dataName')]
    public function testTieBreakerRanking(
        array $products,
        string $term,
        array $searchFields,
        array $fieldScores,
        array $expectedOrder,
    ): void {
        $this->clearElasticsearch();

        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM product');

        static::getContainer()->get(AbstractKeyValueStorage::class)->set(ElasticsearchOptimizeSwitch::FLAG, true);

        static::getContainer()->get('product.repository')->create($products, Context::createDefaultContext());

        $this->setSearchConfiguration(true, $searchFields);
        $this->setSearchScores($fieldScores);

        $this->indexElasticSearch();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm($term);

        $definition = static::getContainer()->get(ProductDefinition::class);
        $result = $this->createEntitySearcher()->search($definition, $criteria, Context::createDefaultContext());

        $resultKeys = array_map(
            static fn (string $id) => self::$ids->getKey($id),
            array_values($result->getIds())
        );

        $topResults = \array_slice($resultKeys, 0, \count($expectedOrder));

        static::assertSame(
            $expectedOrder,
            $topResults,
            \sprintf(
                "Expected ranking [%s] but got [%s].\nFull results: [%s]",
                implode(', ', $expectedOrder),
                implode(', ', $topResults),
                implode(', ', $resultKeys),
            )
        );
    }

    /**
     * @return \Generator<string, array{products: list<array<string, mixed>>, term: string, searchFields: list<string>, fieldScores: array<string, int>, expectedOrder: list<string>}>
     */
    public static function tieBreakerRankingProvider(): \Generator
    {
        self::$ids = $ids = new IdsCollection();

        yield 'Exact match in long name ranks above fuzzy match in short name (field length normalization)' => [
            'products' => [
                self::product($ids, 'stihl-product', 'Stihl Motorsäge MS 271 Farm Boss'),
                self::product($ids, 'stahl-product', 'Stahl Bohrer'),
            ],
            'term' => 'stihl',
            'searchFields' => ['name'],
            'fieldScores' => ['name' => 1000],
            'expectedOrder' => ['stihl-product', 'stahl-product'],
        ];

        yield 'Name exact match ranks above tag-only fuzzy match' => [
            'products' => [
                self::productWithTags($ids, 'stihl-name-match', 'Stihl Motorsäge', ['Benzin', 'Motorsäge']),
                self::productWithTags($ids, 'stahl-tag-match', 'Werkzeug Komplett Set', ['Stahl']),
            ],
            'term' => 'stihl',
            'searchFields' => ['name', 'tags.name'],
            'fieldScores' => ['name' => 1000, 'tags.name' => 500],
            'expectedOrder' => ['stihl-name-match', 'stahl-tag-match'],
        ];

        yield 'Name + manufacturer convergence ranks above name-only match' => [
            'products' => [
                self::productWithManufacturer($ids, 'bosch-full', 'Bosch Akkuschrauber Professional', 'Bosch'),
                self::productWithManufacturer($ids, 'bosch-name-only', 'Bosch Akkuschrauber Professional', 'Makita'),
            ],
            'term' => 'bosch',
            'searchFields' => ['name', 'manufacturer.name'],
            'fieldScores' => ['name' => 500, 'manufacturer.name' => 500],
            'expectedOrder' => ['bosch-full', 'bosch-name-only'],
        ];

        yield 'Name + tag convergence ranks above name-only match' => [
            'products' => [
                self::productWithTags($ids, 'stihl-both', 'Stihl Motorsäge MS 271', ['Stihl', 'Motorsäge']),
                self::productWithTags($ids, 'stihl-name-only', 'Stihl Kettensäge Professional Edition', ['Benzin', 'Profi']),
            ],
            'term' => 'stihl',
            'searchFields' => ['name', 'tags.name'],
            'fieldScores' => ['name' => 500, 'tags.name' => 500],
            'expectedOrder' => ['stihl-both', 'stihl-name-only'],
        ];

        yield 'Phrase match ranks above scattered token match' => [
            'products' => [
                self::product($ids, 'iphone-phrase', 'iPhone Case Transparent'),
                self::product($ids, 'iphone-scattered', 'iPhone Ladegerät Silikon Case'),
            ],
            'term' => 'iphone case',
            'searchFields' => ['name'],
            'fieldScores' => ['name' => 1000],
            'expectedOrder' => ['iphone-phrase', 'iphone-scattered'],
        ];

        yield 'Exact keyword match ranks above prefix-only match' => [
            'products' => [
                self::product($ids, 'bosch-exact', 'Bosch Akku Schrauber'),
                self::product($ids, 'bosch-prefix', 'Boschung Premium Reiniger'),
            ],
            'term' => 'bosch',
            'searchFields' => ['name'],
            'fieldScores' => ['name' => 1000],
            'expectedOrder' => ['bosch-exact', 'bosch-prefix'],
        ];

        yield 'Multi-field match outranks single-field match with same term' => [
            'products' => [
                self::productWithTags(
                    $ids,
                    'samsung-multi',
                    'Samsung Galaxy S24',
                    ['Samsung', 'Galaxy', 'Smartphone'],
                ),
                self::productWithTags(
                    $ids,
                    'samsung-single',
                    'Samsung Galaxy Tab A9',
                    ['Tablet', 'Entertainment'],
                ),
            ],
            'term' => 'samsung',
            'searchFields' => ['name', 'tags.name'],
            'fieldScores' => ['name' => 500, 'tags.name' => 500],
            'expectedOrder' => ['samsung-multi', 'samsung-single'],
        ];
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    /**
     * @return array<string, mixed>
     */
    private static function product(IdsCollection $ids, string $key, string $name): array
    {
        return (new ProductBuilder($ids, $key))
            ->name($name)
            ->price(100)
            ->visibility()
            ->build();
    }

    /**
     * @param list<string> $tags
     *
     * @return array<string, mixed>
     */
    private static function productWithTags(IdsCollection $ids, string $key, string $name, array $tags): array
    {
        $builder = (new ProductBuilder($ids, $key))
            ->name($name)
            ->price(100)
            ->visibility();

        foreach ($tags as $tag) {
            $builder->tag($tag);
        }

        return $builder->build();
    }

    /**
     * @return array<string, mixed>
     */
    private static function productWithManufacturer(IdsCollection $ids, string $key, string $name, string $manufacturer): array
    {
        return (new ProductBuilder($ids, $key))
            ->name($name)
            ->manufacturer($manufacturer)
            ->price(100)
            ->visibility()
            ->build();
    }
}
