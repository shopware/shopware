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
 * Tests that the custom BM25 similarity configuration (b=0 for structured fields,
 * b=0.75 for long-form text) produces correct ranking behavior.
 *
 * @internal
 */
#[Package('inventory')]
class BM25SimilarityRankingTest extends TestCase
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
     * @param 'strict'|'equal_scores'|'different_scores' $scoreAssertion
     *                                                                   - strict: asserts exact ranking order
     *                                                                   - equal_scores: asserts all expected products have near-equal scores (proves b=0)
     *                                                                   - different_scores: asserts significant score gap between products (proves sw_length_norm b=0.75)
     */
    #[DataProvider('similarityRankingProvider')]
    #[TestDox('$_dataName')]
    public function testSimilarityRanking(
        array $products,
        string $term,
        array $searchFields,
        array $fieldScores,
        array $expectedOrder,
        string $scoreAssertion = 'strict',
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

        $scores = [];
        foreach ($expectedOrder as $key) {
            $scores[$key] = $result->getScore(self::$ids->get($key));
        }

        $values = array_values($scores);
        static::assertNotEmpty($values, 'No scores found for expected products');
        $max = max($values);
        $min = min($values);
        $ratio = $max > 0 ? $min / $max : 1.0;

        switch ($scoreAssertion) {
            case 'equal_scores':
                // With b=0, scores should be nearly identical.
                // Ratio > 0.95 means < 5% difference — tight enough to catch b=0.75
                // (which causes 50%+ difference) while tolerating floating-point rounding.
                static::assertGreaterThan(0.95, $ratio, \sprintf(
                    'Expected near-equal scores (ratio > 0.95) for [%s] but got ratio %.4f: %s',
                    implode(', ', $expectedOrder),
                    $ratio,
                    json_encode($scores, \JSON_THROW_ON_ERROR)
                ));
                break;

            case 'different_scores':
                // With sw_length_norm (b=0.75), short fields score significantly higher.
                // Ratio < 0.8 means > 20% difference — proves length normalization is active.
                static::assertLessThan(0.8, $ratio, \sprintf(
                    "Expected significant score gap (ratio < 0.8) for [%s] but got ratio %.4f: %s\nLength normalization (sw_length_norm) may not be applied to this field.",
                    implode(', ', $expectedOrder),
                    $ratio,
                    json_encode($scores, \JSON_THROW_ON_ERROR)
                ));
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
                break;

            default:
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
    }

    /**
     * @return \Generator<string, array{products: list<array<string, mixed>>, term: string, searchFields: list<string>, fieldScores: array<string, int>, expectedOrder: list<string>, scoreAssertion?: 'strict'|'equal_scores'|'different_scores'}>
     */
    public static function similarityRankingProvider(): \Generator
    {
        self::$ids = $ids = new IdsCollection();

        yield 'Long descriptive name should not be penalized vs short name (b=0)' => [
            'products' => [
                self::product($ids, 'sony-long', 'Sony Bravia XR 65-inch 4K Ultra HD Smart OLED TV with Dolby Vision IQ Dolby Atmos Google Assistant Built-in Hands-free Voice Control'),
                self::product($ids, 'sony-short', 'Sony TV'),
            ],
            'term' => 'sony',
            'searchFields' => ['name'],
            'fieldScores' => ['name' => 1000],
            'expectedOrder' => ['sony-long', 'sony-short'],
            'scoreAssertion' => 'equal_scores',
        ];

        yield 'Name match outranks tag fuzzy match regardless of field length' => [
            'products' => [
                self::productWithTags($ids, 'stihl-name', 'Stihl Motorsäge MS 271 Farm Boss', ['Benzin', 'Motorsäge']),
                self::productWithTags($ids, 'stahl-tag', 'Werkzeug Komplett Set', ['Stahl']),
            ],
            'term' => 'stihl',
            'searchFields' => ['name', 'tags.name'],
            'fieldScores' => ['name' => 1000, 'tags.name' => 500],
            'expectedOrder' => ['stihl-name', 'stahl-tag'],
        ];

        yield 'Exact keyword match in long name beats prefix match in short name' => [
            'products' => [
                self::product($ids, 'bosch-exact', 'Bosch Akku Schrauber Professional Edition'),
                self::product($ids, 'bosch-prefix', 'Boschung Reiniger'),
            ],
            'term' => 'bosch',
            'searchFields' => ['name'],
            'fieldScores' => ['name' => 1000],
            'expectedOrder' => ['bosch-exact', 'bosch-prefix'],
        ];

        yield 'Multi-field convergence ranks above single-field match' => [
            'products' => [
                self::productWithTags($ids, 'samsung-multi', 'Samsung Galaxy S24', ['Samsung', 'Galaxy', 'Smartphone']),
                self::productWithTags($ids, 'samsung-single', 'Samsung Galaxy Tab A9', ['Tablet', 'Entertainment']),
            ],
            'term' => 'samsung',
            'searchFields' => ['name', 'tags.name'],
            'fieldScores' => ['name' => 500, 'tags.name' => 500],
            'expectedOrder' => ['samsung-multi', 'samsung-single'],
        ];

        // Filler products increase IDF for "waterproof" so the BM25-scored MatchQuery
        // (boost 0.8) outscores the ConstantScore prefix query (boost 0.4).
        // With 10 docs and only 2 containing "waterproof", IDF ≈ 1.5, making MatchQuery the
        // dis_max winner — which is where sw_length_norm (b=0.75) creates observable score differences.
        $fillerProducts = [];
        for ($i = 1; $i <= 8; ++$i) {
            $fillerProducts[] = self::productWithDescription($ids, "filler-$i", "Product $i", "Generic description without the target keyword number $i");
        }

        yield 'Description field uses length normalization (sw_length_norm b=0.75)' => [
            'products' => array_merge([
                self::productWithDescription($ids, 'waterproof-focused', 'Outdoor Gear A', 'Waterproof jacket with sealed seams'),
                self::productWithDescription($ids, 'waterproof-buried', 'Outdoor Gear B', 'This premium outdoor jacket features a scratch-resistant coating with anti-reflective layer a titanium zipper GPS pocket heart rate strap barometer altimeter compass and is waterproof to withstand the harshest weather conditions making it the perfect companion for any outdoor expedition across mountains deserts and tundra'),
            ], $fillerProducts),
            'term' => 'waterproof',
            'searchFields' => ['description'],
            'fieldScores' => ['description' => 1000],
            'expectedOrder' => ['waterproof-focused', 'waterproof-buried'],
            'scoreAssertion' => 'different_scores',
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
    private static function productWithDescription(IdsCollection $ids, string $key, string $name, string $description): array
    {
        return (new ProductBuilder($ids, $key))
            ->name($name)
            ->description($description)
            ->price(100)
            ->visibility()
            ->build();
    }
}
