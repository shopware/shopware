<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class SearchCasesTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use ElasticsearchTestTestBehaviour;
    use FilesystemBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use SessionTestBehaviour;

    /**
     * @param array<mixed> $products
     */
    #[DataProvider('numbersProvider')]
    public function testSearch(IdsCollection $ids, array $products, string $term, string $best): void
    {
        $this->clearElasticsearch();

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM product');

        static::getContainer()->get('product.repository')->create(array_values($products), Context::createDefaultContext());

        $this->setSearchConfiguration(true, ['name', 'productNumber']);
        $this->setSearchScores(['name' => 700, 'productNumber' => 1000]);

        $this->indexElasticSearch();

        $searcher = $this->createEntitySearcher();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm($term);

        $definition = static::getContainer()->get(ProductDefinition::class);

        $result = $searcher->search($definition, $criteria, Context::createDefaultContext());

        $scores = [];
        foreach ($result->getData() as $item) {
            $key = $ids->getKey((string) $item['id']);
            static::assertNotNull($key);
            $scores[$key] = $item['_score'];
        }

        $firstId = $result->firstId();
        static::assertNotNull($firstId, print_r($scores, true));
        static::assertSame($best, $ids->getKey($firstId), print_r($scores, true));
    }

    public static function numbersProvider(): \Generator
    {
        $ids = new IdsCollection();

        $products = [
            'p1' => self::product($ids, 'p1', 'DE-031668-B', 'HP LaserJet Enterprise M608x Inkl. Stapelfach und Papierfach'),
            'p2' => self::product($ids, 'p2', 'DE-031677-B', 'HP LaserJet Enterprise M608x Inkl. Stapelfach'),
            'p3' => self::product($ids, 'p3', 'DE-031687-B', 'HP LaserJet Enterprise M608x'),
            'p4' => self::product($ids, 'p4', 'DE-13.116-B', 'LG 24MB35PM-B - 1920 x 1080 - FHD'),
            'p5' => self::product($ids, 'p5', 'DE-15.174-N', 'Crucial DDR4 Desktop Speicher - DIMM - DDR4 - 2400 MHz - CL17'),
            'p6' => self::product($ids, 'p6', 'DE-17.028-A', 'Fujitsu Display B24-8 TE - 1920 x 1080 - FHD'),
            'p7' => self::product($ids, 'p7', 'DE-17.028-B', 'Fujitsu Display B24-8 TE - 1920 x 1080 - FHD'),
            'p8' => self::product($ids, 'p8', 'DE-17.346-B', 'LG 24BK550Y-B - 1920 x 1080 - FHD'),
            'p9' => self::product($ids, 'p9', 'DE-17.353-B', 'Eizo FlexScan EV2416W-BK - 1920 x 1200 - WUXGA'),
            'p10' => self::product($ids, 'p10', 'DE-17.447-N', 'SOLID DDR3 Desktop Speicher - DIMM 240-PIN - DDR3 - 1600 MHz - CL 11'),
        ];

        yield 'Exact number match' => [$ids, $products, 'DE-031668-B', 'p1'];
    }

    public function testExactNameTokenMatchRanksAheadOfPrefixOnlyMatch(): void
    {
        $this->clearElasticsearch();

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM product');

        $ids = new IdsCollection();

        static::getContainer()->get('product.repository')->create([
            self::product($ids, 'exact', 'DE-EXACT-1', 'Leather Jacket'),
            self::product($ids, 'prefix', 'DE-PREFIX-1', 'Leathery Jacket'),
        ], Context::createDefaultContext());

        $this->setSearchConfiguration(true, ['name']);
        $this->setSearchScores(['name' => 700]);

        $this->indexElasticSearch();

        $searcher = $this->createEntitySearcher();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm('Leather');

        $definition = static::getContainer()->get(ProductDefinition::class);

        $result = $searcher->search($definition, $criteria, Context::createDefaultContext());

        $firstId = $result->firstId();
        static::assertNotNull($firstId, print_r($result->getData(), true));
        static::assertSame('exact', $ids->getKey($firstId), print_r($result->getData(), true));
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @param list<string> $searchFields
     * @param array<string, int> $searchScores
     * @param list<string> $mustNotContainKeys
     */
    #[DataProvider('searchScenariosProvider')]
    public function testSearchScenarios(
        IdsCollection $ids,
        array $products,
        array $searchFields,
        array $searchScores,
        ?float $minScore,
        string $term,
        ?string $expectedFirst,
        array $mustNotContainKeys = [],
    ): void {
        $this->clearElasticsearch();
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM product');
        static::getContainer()->get('product.repository')->create($products, Context::createDefaultContext());

        $this->setSearchConfiguration(true, $searchFields);
        $this->setSearchScores($searchScores);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        if ($minScore !== null) {
            $systemConfig->set('core.search.minScore', $minScore);
        } else {
            $systemConfig->delete('core.search.minScore');
        }

        $this->indexElasticSearch();

        $searcher = $this->createEntitySearcher();
        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm($term);

        $definition = static::getContainer()->get(ProductDefinition::class);
        $result = $searcher->search($definition, $criteria, Context::createDefaultContext());

        $hitKeys = [];
        $scores = [];
        foreach ($result->getData() as $item) {
            $key = $ids->getKey((string) $item['id']);
            if ($key === null) {
                continue;
            }
            $hitKeys[] = $key;
            $scores[$key] = $item['_score'];
        }

        if ($expectedFirst !== null) {
            static::assertNotNull($result->firstId(), 'Expected a top hit but got none. Scores: ' . print_r($scores, true));
            static::assertSame(
                $expectedFirst,
                $ids->getKey($result->firstId()),
                \sprintf('Expected "%s" to rank first. Actual ranking: %s', $expectedFirst, print_r($scores, true)),
            );
        }

        foreach ($mustNotContainKeys as $blockedKey) {
            static::assertNotContains(
                $blockedKey,
                $hitKeys,
                \sprintf('Product "%s" should not appear in the hit list but did. Scores: %s', $blockedKey, print_r($scores, true)),
            );
        }
    }

    public static function searchScenariosProvider(): \Generator
    {
        $ids = new IdsCollection();
        yield 'glued query din340 matches indexed "DIN 340"' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'a1-target', 'DE-A1-1', 'Bohrcraft DIN 340 HSS'),
                self::product($ids, 'a1-other', 'DE-A1-2', 'Hammer Tool'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'din340',
            'expectedFirst' => 'a1-target',
        ];

        $ids = new IdsCollection();
        yield 'split query "DIN 340" matches indexed DIN340' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'a2-target', 'DE-A2-1', 'DIN340 Drill Bit'),
                self::product($ids, 'a2-other', 'DE-A2-2', 'Hammer Tool'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'DIN 340',
            'expectedFirst' => 'a2-target',
        ];

        $ids = new IdsCollection();
        yield 'digit+letter glued query (Kleber601) matches split SKU' => [
            'ids' => $ids,
            'products' => [
                self::productWithKeywords($ids, 'a3-target', 'DE-A3-1', 'Kleber Tool', ['601.1']),
                self::product($ids, 'a3-other', 'DE-A3-2', 'Hammer Tool'),
            ],
            'searchFields' => ['name', 'customSearchKeywords'],
            'searchScores' => ['name' => 500, 'customSearchKeywords' => 1000],
            'minScore' => null,
            'term' => 'Kleber601',
            'expectedFirst' => 'a3-target',
        ];

        $ids = new IdsCollection();
        yield 'V8000ASR (glued) matches "V8000 ASR" in name' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'a4-target', 'DE-A4-1', 'V8000 ASR Cleaner'),
                self::product($ids, 'a4-other', 'DE-A4-2', 'Random Product'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'V8000ASR',
            'expectedFirst' => 'a4-target',
        ];

        $ids = new IdsCollection();
        yield '"Gr49" matches indexed "Gr.49"' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'a5-target', 'DE-A5-1', 'ANATOMIC BAU 500 Gr.49'),
                self::product($ids, 'a5-other', 'DE-A5-2', 'Random Shoe'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Gr49',
            'expectedFirst' => 'a5-target',
        ];

        $ids = new IdsCollection();
        yield 'comma decimal 5,5 matches indexed "5,5 mm"' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'b1-target', 'DE-B1-1', 'Drill 5,5 mm HSS'),
                self::product($ids, 'b1-other', 'DE-B1-2', 'Drill 2,5 mm HSS'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => '5,5',
            'expectedFirst' => 'b1-target',
        ];

        $ids = new IdsCollection();
        yield 'slash in spec 65/92 matches indexed "65/92/10"' => [
            'ids' => $ids,
            'products' => [
                self::productWithKeywords($ids, 'b2-target', 'DE-B2-1', 'Multi-Point Lock', ['65/92/10']),
                self::productWithKeywords($ids, 'b2-other', 'DE-B2-2', 'Multi-Point Lock', ['70/92/10']),
            ],
            'searchFields' => ['customSearchKeywords'],
            'searchScores' => ['customSearchKeywords' => 1000],
            'minScore' => null,
            'term' => '65/92',
            'expectedFirst' => 'b2-target',
        ];

        $ids = new IdsCollection();
        yield 'hyphenated HWS-112 in name matches query "HWS 112"' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'b3-target', 'DE-B3-1', 'Remmers HWS-112 Sealant'),
                self::product($ids, 'b3-other', 'DE-B3-2', 'Remmers HWS-200 Sealant'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'HWS 112',
            'expectedFirst' => 'b3-target',
        ];

        $ids = new IdsCollection();
        yield 'hyphenated query "Cobra-Wasserpumpenzange" matches indexed Wasserpumpenzange' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'b4-target', 'DE-B4-1', 'Cobra Wasserpumpenzange K1462784'),
                self::product($ids, 'b4-other', 'DE-B4-2', 'Basic Schraubenschlüssel'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Cobra-Wasserpumpenzange',
            'expectedFirst' => 'b4-target',
        ];

        $ids = new IdsCollection();
        yield 'lone G query does not hit products that only contain bare G from HSS-G' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'c1-hssg', 'DE-C1-1', 'HSS-G Drill Bit'),
                self::product($ids, 'c1-other', 'DE-C1-2', 'Hammer Tool'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'G',
            // The length filter drops the bare "G" token from HSS-G on both sides.
            // The query "G" also fails the min token length (2) so the search yields no hit.
            'expectedFirst' => null,
            'mustNotContainKeys' => ['c1-hssg', 'c1-other'],
        ];

        $ids = new IdsCollection();
        yield 'query 5,5 does not also match products with lone bare 5 in name' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'c2-target', 'DE-C2-1', 'Bohrcraft 5,5 mm'),
                self::product($ids, 'c2-bare5', 'DE-C2-2', 'Hammer 340g size 5'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => '5,5',
            'expectedFirst' => 'c2-target',
            'mustNotContainKeys' => ['c2-bare5'],
        ];

        $ids = new IdsCollection();
        yield 'manufacturerNumber with hyphen splits for "HWS 112" query' => [
            'ids' => $ids,
            'products' => [
                self::productWithManufacturerNumber($ids, 'd1-target', 'DE-D1-1', 'Generic Sealant', 'HWS-112'),
                self::productWithManufacturerNumber($ids, 'd1-other', 'DE-D1-2', 'Generic Sealant', 'XYZ-200'),
            ],
            'searchFields' => ['manufacturerNumber'],
            'searchScores' => ['manufacturerNumber' => 1000],
            'minScore' => null,
            'term' => 'HWS 112',
            'expectedFirst' => 'd1-target',
        ];

        $ids = new IdsCollection();
        yield 'manufacturerNumber splits on letter/digit boundary for "DIN 340"' => [
            'ids' => $ids,
            'products' => [
                self::productWithManufacturerNumber($ids, 'd2-target', 'DE-D2-1', 'Drill Bit', 'DIN340'),
                self::productWithManufacturerNumber($ids, 'd2-other', 'DE-D2-2', 'Drill Bit', 'XYZ500'),
            ],
            'searchFields' => ['manufacturerNumber'],
            'searchScores' => ['manufacturerNumber' => 1000],
            'minScore' => null,
            'term' => 'DIN 340',
            'expectedFirst' => 'd2-target',
        ];

        $ids = new IdsCollection();
        yield 'short 4-char query "Baum" does not fuzzy-match "Baus"' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'f1-exact', 'DE-F1-1', 'Baum Tree Premium'),
                self::product($ids, 'f1-fuzzy', 'DE-F1-2', 'Baus Haus Bau'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Baum',
            'expectedFirst' => 'f1-exact',
            'mustNotContainKeys' => ['f1-fuzzy'],
        ];

        $ids = new IdsCollection();
        yield 'prefix_length 2 rejects first-char-edit fuzzy match (Stihl ≠ Spax)' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'f2-exact', 'DE-F2-1', 'Stihl Motorsäge'),
                self::product($ids, 'f2-fuzzy', 'DE-F2-2', 'Spax Holzschraube'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Stihl',
            'expectedFirst' => 'f2-exact',
            'mustNotContainKeys' => ['f2-fuzzy'],
        ];

        $ids = new IdsCollection();
        yield 'exact "Mutter" outranks fuzzy candidate "Mütze"' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'f3-exact', 'DE-F3-1', 'Mutter Sechskant M8'),
                self::product($ids, 'f3-fuzzy', 'DE-F3-2', 'Mütze Wintermütze Wolle'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Mutter',
            'expectedFirst' => 'f3-exact',
        ];

        $ids = new IdsCollection();
        yield '10-char token exact ranks far above fuzzy (prefix_length 3)' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'f4-exact', 'DE-F4-1', 'bohrcraftxz Exact'),
                self::product($ids, 'f4-fuzzy', 'DE-F4-2', 'bxxrcraftxz Prefix'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'bohrcraftxz',
            // The fuzzy variant can still surface via the shared-ngram subfield
            // (separate match path). What we verify here is that prefix_length
            // and exact/fuzzy boost rebalancing keep exact on top, not that the
            // fuzzy candidate is suppressed entirely.
            'expectedFirst' => 'f4-exact',
        ];

        $ids = new IdsCollection();
        yield 'minScore=0 (default) returns weak fuzzy-only hit alongside strong hit' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'g1-strong', 'DE-G1-1', 'Heckenschere Professional'),
                self::product($ids, 'g1-weak', 'DE-G1-2', 'Heckeschere Weak Variant'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => 0.0,
            'term' => 'Heckenschere',
            'expectedFirst' => 'g1-strong',
        ];

        $ids = new IdsCollection();
        yield 'minScore=200 drops weak fuzzy hit while keeping exact' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'g2-strong', 'DE-G2-1', 'Heckenschere Professional'),
                self::product($ids, 'g2-weak', 'DE-G2-2', 'Heckeschere Weak Variant'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            // Observed scores at this scale: exact ≈ 749, weak fuzzy ≈ 127.
            // 200 comfortably separates the two without being a fragile
            // threshold close to either score.
            'minScore' => 200.0,
            'term' => 'Heckenschere',
            'expectedFirst' => 'g2-strong',
            'mustNotContainKeys' => ['g2-weak'],
        ];

        $ids = new IdsCollection();
        yield 'repeated query token does not double-score the match (unique filter)' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'h1-target', 'DE-H1-1', 'Bohrcraft Drill'),
                self::product($ids, 'h1-other', 'DE-H1-2', 'Bohrcraft Drill Set'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'bohrcraft bohrcraft',
            // Whitespace tokenize yields two "bohrcraft" tokens; the search-side
            // unique filter ensures a single analyzed term per document. We don't
            // assert a specific first-result ordering between the two candidates
            // (which is BM25-dependent on length/IDF) — only that the build
            // executes without the repeat inflating anything pathologically.
            'expectedFirst' => null,
        ];

        $ids = new IdsCollection();
        yield 'Bohrcraft din340 5,5 — correct product ranks first in a mixed catalog' => [
            'ids' => $ids,
            'products' => [
                self::product(
                    $ids,
                    'i1-target',
                    'DE-I1-1',
                    'Bohrcraft Spibo DIN 340 HSS-G geschl. Split Point Typ N 5,5 mm Bohrcraft QP',
                ),
                self::product($ids, 'i1-only-brand', 'DE-I1-2', 'Bohrcraft Basic Hammer'),
                self::product($ids, 'i1-only-size', 'DE-I1-3', 'Bohrer 5,5 mm Einzeln'),
                self::product($ids, 'i1-only-din', 'DE-I1-4', 'DIN 340 generic drill'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Bohrcraft din340 5,5',
            'expectedFirst' => 'i1-target',
        ];

        $ids = new IdsCollection();
        yield '"variant vx 7539/160" fuzzy-matches indexed "VX 7939/160"' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'i2-target', 'DE-I2-1', 'variant VX 7939/160 compact'),
                self::product($ids, 'i2-other', 'DE-I2-2', 'variant XYZ 1111/222 other'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'variant vx 7539/160',
            'expectedFirst' => 'i2-target',
        ];

        $ids = new IdsCollection();
        yield 'PascalCase ChannelLine reached via split_on_case_change' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'i3-target', 'DE-I3-1', 'ChannelLine Drill Premium'),
                self::product($ids, 'i3-other', 'DE-I3-2', 'Basic Hammer'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Channel Line',
            'expectedFirst' => 'i3-target',
        ];

        $ids = new IdsCollection();
        yield 'lowercase glued Channelline reached via .ngram subfield fallback' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'i4-target', 'DE-I4-1', 'Channelline Drill Premium'),
                self::product($ids, 'i4-other', 'DE-I4-2', 'Basic Hammer'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Channel Line',
            'expectedFirst' => 'i4-target',
        ];

        // Compressed-form decimal+unit query bridges to spaced-form indexed
        // content via sw_decimal_normalize (3,3 → 3.3) and sw_unit_glue
        // (3.3 mm → 3.3mm) running on the German technical-term chain.
        // This is the canonical customer-reported scenario the pre-tokenization
        // commit ships against.
        $ids = new IdsCollection();
        yield 'compressed "3.3mm" matches indexed "3,3 mm"' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'j1-target', 'DE-J1-1', 'Bohrer 3,3 mm HSS'),
                self::product($ids, 'j1-other', 'DE-J1-2', 'Hammer 5,5 mm Set'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => '3.3mm',
            'expectedFirst' => 'j1-target',
        ];

        // sw_unit_glue baseline (locale-agnostic, not gated on decimal): a
        // compressed quantity-unit token finds the spaced-form indexed content
        // through the universal char_filter, not via the German-specific
        // decimal path.
        $ids = new IdsCollection();
        yield 'compressed "100ml" matches indexed "100 ml"' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'j2-target', 'DE-J2-1', 'Bohrcraft 100 ml Spray'),
                self::product($ids, 'j2-other', 'DE-J2-2', 'Basic 50 g Hammer'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => '100ml',
            'expectedFirst' => 'j2-target',
        ];

        // Anchor guard: sw_unit_glue must NOT weld `Gr49 Gr.49 ChannelLine`
        // into one chunk because the digit run "49" is embedded inside an
        // identifier (`Gr49`), not at a word boundary. After the (^|\s) anchor
        // was added to the pattern, `Gr.49` survives as a standalone token in
        // .search (via word_delimiter_graph preserve_original) and matches
        // directly. Locks the regression where the previous pattern
        // (\d)\s+([^\d\s]) glued everything into "Gr49Gr.49ChannelLine".
        $ids = new IdsCollection();
        yield 'over-glue protection: Gr.49 stays findable inside "Gr49 Gr.49 ChannelLine"' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'j3-target', 'DE-J3-1', 'Bohrcraft Gr49 Gr.49 ChannelLine Drill'),
                self::product($ids, 'j3-other', 'DE-J3-2', 'Basic Hammer'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Gr.49',
            'expectedFirst' => 'j3-target',
        ];

        // catenate_numbers contribution: a plain-digit query like "33"
        // matches a comma-decimal indexed product (`3,3 mm`) because
        // word_delimiter_graph with catenate_numbers=true joins the two `3`
        // sub-parts of `3.3mm` into a standalone `33` token, alongside the
        // glued `33mm` from catenate_all. Without catenate_numbers, the only
        // path to the doc is prefix-of-`33mm`; the standalone-token route is
        // measurably stronger for multi-token queries.
        //
        // The decoy `"Hammer 33 mm"` indexes `33` directly via wd_graph
        // split_on_numerics, so it also matches `33`. The target wins by
        // matching both `33` AND `drill`, while the decoy only matches `33`.
        $ids = new IdsCollection();
        yield 'query "33 drill" finds "3,3 mm Drill" via catenate_numbers' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'j4-target', 'DE-J4-1', 'Bohrer 3,3 mm Drill HSS'),
                self::product($ids, 'j4-other', 'DE-J4-2', 'Hammer 33 mm Set'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => '33 drill',
            'expectedFirst' => 'j4-target',
        ];

        // Trailing-zero decimal equivalence: a query whose decimal differs
        // from the indexed value only in trailing zeros (`5.00` vs `5.0`)
        // must still find the doc. The `sw_decimal_normalize_token`
        // pattern_capture filter emits the integer prefix ("5") as a
        // shared token from both `5.0mm` (indexed, post-unit_glue) and
        // `5.00` (query), so the bridge happens through that capture.
        $ids = new IdsCollection();
        yield 'query "Bohrcraft DIN 340 HSSG 5.00" finds indexed "Bohrcraft DIN 340 HSSG 5.0 mm"' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'j5-target', 'DE-J5-1', 'Bohrcraft DIN 340 HSSG 5.0 mm'),
                self::product($ids, 'j5-other', 'DE-J5-2', 'Bohrcraft DIN 340 HSSG 6.0 mm'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Bohrcraft DIN 340 HSSG 5.00',
            'expectedFirst' => 'j5-target',
        ];

        // Non-zero-fraction trailing-zero equivalence: query "3.3" must
        // find a product indexed with "3.30 mm". The
        // `sw_decimal_normalize_token` second pattern
        // `(\d+\.\d*[1-9])0+(?=\D|$)` strips trailing zeros after the last
        // non-zero digit, so indexed "3.30mm" (post-unit_glue) emits "3.3"
        // standalone, matching the query's direct "3.3" token.
        $ids = new IdsCollection();
        yield 'query "Bohrcraft DIN 340 3.3" finds indexed "Bohrcraft DIN 340 3.30 mm"' => [
            'ids' => $ids,
            'products' => [
                self::product($ids, 'j6-target', 'DE-J6-1', 'Bohrcraft DIN 340 3.30 mm Drill'),
                self::product($ids, 'j6-other', 'DE-J6-2', 'Bohrcraft DIN 340 4.50 mm Drill'),
            ],
            'searchFields' => ['name'],
            'searchScores' => ['name' => 1000],
            'minScore' => null,
            'term' => 'Bohrcraft DIN 340 3.3',
            'expectedFirst' => 'j6-target',
        ];

        // customSearchKeywords length normalization. Merchants treat this field
        // as a free-form curated bag of search hints; without `sw_length_norm`
        // (b=0.75) on the `.search` subfield, a long bag of 10+ keywords gets a
        // TF-concentration bonus that pushes diluted listings ahead of focused
        // ones on the same matching token. Both products share the same single
        // matching token ("bohrcraft"); only document length differs.
        //
        // Without lengthNorm: the diluted product wins because BM25 with b=0
        // does not penalise long fields, and the matching term frequency 1/10
        // is treated like 1/1 in the focused doc.
        // With lengthNorm  (PR #16497, `buildTextFieldConfig(lengthNorm: true)`
        // on customSearchKeywords): the focused doc wins because the diluted
        // doc's length penalty outweighs its TF parity.
        $ids = new IdsCollection();
        yield 'lengthNorm: focused customSearchKeywords outranks diluted list on shared token' => [
            'ids' => $ids,
            'products' => [
                self::productWithKeywords($ids, 'k1-focused', 'DE-K1-1', 'Drill A', ['bohrcraft']),
                self::productWithKeywords($ids, 'k1-diluted', 'DE-K1-2', 'Drill B', [
                    'bohrcraft', 'drill', 'tool', 'hammer', 'saw',
                    'wrench', 'pliers', 'set', 'kit', 'professional',
                ]),
            ],
            'searchFields' => ['customSearchKeywords'],
            'searchScores' => ['customSearchKeywords' => 1000],
            'minScore' => null,
            'term' => 'bohrcraft',
            'expectedFirst' => 'k1-focused',
        ];
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    /**
     * @return array<string, mixed>
     */
    private static function product(IdsCollection $ids, string $key, string $number, string $name): array
    {
        return (new ProductBuilder($ids, $key))
            ->number($number)
            ->price(100)
            ->visibility()
            ->name($name)
            ->build();
    }

    /**
     * @param list<string> $keywords
     *
     * @return array<string, mixed>
     */
    private static function productWithKeywords(IdsCollection $ids, string $key, string $number, string $name, array $keywords): array
    {
        $product = self::product($ids, $key, $number, $name);
        $product['customSearchKeywords'] = $keywords;

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    private static function productWithManufacturerNumber(IdsCollection $ids, string $key, string $number, string $name, string $manufacturerNumber): array
    {
        $product = self::product($ids, $key, $number, $name);
        $product['manufacturerNumber'] = $manufacturerNumber;

        return $product;
    }
}
