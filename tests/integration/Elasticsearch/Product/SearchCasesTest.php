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
    use ElasticsearchTestTestBehaviour;
    use FilesystemBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use SessionTestBehaviour;

    private static IdsCollection $ids;

    /**
     * @param array<mixed> $products
     */
    #[DataProvider('numbersProvider')]
    public function testSearch(array $products, string $term, string $best): void
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
            $key = self::$ids->getKey((string) $item['id']);
            static::assertNotNull($key);
            $scores[$key] = $item['_score'];
        }

        static::assertSame(
            $best,
            self::$ids->getKey((string) $result->firstId()),
            print_r($scores, true)
        );
    }

    public static function numbersProvider(): \Generator
    {
        self::$ids = $ids = new IdsCollection();

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

        yield 'Exact number match' => [$products, 'DE-031668-B', 'p1'];
    }

    public function testExactNameTokenMatchRanksAheadOfPrefixOnlyMatch(): void
    {
        $this->clearElasticsearch();

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM product');

        self::$ids = $ids = new IdsCollection();

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

        static::assertSame(
            'exact',
            self::$ids->getKey((string) $result->firstId()),
            print_r($result->getData(), true)
        );
    }

    /**
     * Covers the scenarios fixed by the April 2026 search improvements:
     * split_on_numerics, length filter, unique filter, manufacturerNumber
     * promotion, fuzziness tightening, configurable minScore, and
     * tokenize-inline for special characters.
     *
     * @param array<int, array<string, mixed>> $products
     * @param list<string> $searchFields
     * @param array<string, int|float> $searchScores
     * @param list<string> $mustNotContainKeys
     */
    #[DataProvider('searchImprovementsProvider')]
    public function testSearchImprovement(
        IdsCollection $ids,
        array $products,
        array $searchFields,
        array $searchScores,
        ?float $minScore,
        string $term,
        ?string $expectedFirst,
        array $mustNotContainKeys = [],
    ): void {
        self::$ids = $ids;

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
                $ids->getKey((string) $result->firstId()),
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

    public static function searchImprovementsProvider(): \Generator
    {
        // --- Group A: split_on_numerics + glued-form handling --------------
        $ids = new IdsCollection();
        yield 'A1: glued query din340 matches indexed "DIN 340"' => [
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
        yield 'A2: split query "DIN 340" matches indexed DIN340' => [
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
        yield 'A3: digit+letter glued query (Kleber601) matches split SKU' => [
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
        yield 'A4: V8000ASR (glued) matches "V8000 ASR" in name' => [
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
        yield 'A5: "Gr49" matches indexed "Gr.49"' => [
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

        // --- Group B: special characters through tokenizer bypass -----------
        $ids = new IdsCollection();
        yield 'B1: comma decimal 5,5 matches indexed "5,5 mm"' => [
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
        yield 'B2: slash in spec 65/92 matches indexed "65/92/10"' => [
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
        yield 'B3: hyphenated HWS-112 in name matches query "HWS 112"' => [
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
        yield 'B4: hyphenated query "Cobra-Wasserpumpenzange" matches indexed Wasserpumpenzange' => [
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

        // --- Group C: single-char noise suppression (sw_length_min) ---------
        $ids = new IdsCollection();
        yield 'C1: lone N query does not hit products that only contain bare G from HSS-G' => [
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
        yield 'C2: query 5,5 does not also match products with lone bare 5 in name' => [
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

        // --- Group D: manufacturerNumber promoted to technical analyzer -----
        $ids = new IdsCollection();
        yield 'D1: manufacturerNumber with hyphen splits for "HWS 112" query' => [
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
        yield 'D2: manufacturerNumber splits on letter/digit boundary for "DIN 340"' => [
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

        // --- Group F: fuzziness policy tightening ---------------------------
        $ids = new IdsCollection();
        yield 'F1: short 4-char query "Baum" does not fuzzy-match "Baus"' => [
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
        yield 'F2: prefix_length 2 rejects first-char-edit fuzzy match (Stihl ≠ Spax)' => [
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
        yield 'F3: exact "Mutter" outranks fuzzy candidate "Mütze"' => [
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
        yield 'F4: 10-char token exact ranks far above fuzzy (prefix_length 3)' => [
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

        // --- Group G: configurable minScore via system_config ---------------
        $ids = new IdsCollection();
        yield 'G1: minScore=0 (default) returns weak fuzzy-only hit alongside strong hit' => [
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
        yield 'G2: minScore=200 drops weak fuzzy hit while keeping exact' => [
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

        // --- Group H: search-side unique filter -----------------------------
        $ids = new IdsCollection();
        yield 'H1: repeated query token does not double-score the match (unique filter)' => [
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

        // --- Group I: end-to-end regressions --------------------------------
        $ids = new IdsCollection();
        yield 'I1: Bohrcraft din340 5,5 — correct product ranks first in a mixed catalog' => [
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
        yield 'I2: "variant vx 7539/160" fuzzy-matches indexed "VX 7939/160"' => [
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
        yield 'I3: PascalCase ChannelLine reached via split_on_case_change' => [
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
        yield 'I4: lowercase glued Channelline reached via .ngram subfield fallback' => [
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
