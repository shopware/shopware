<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\FullText\SimpleQueryStringQuery;
use OpenSearchDSL\Search;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminSearcher;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Test\AdminElasticsearchTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class AdminSearcherTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AdminElasticsearchTestBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private AdminSearcher $searcher;

    protected function setUp(): void
    {
        if (!static::getContainer()->getParameter('elasticsearch.administration.enabled')) {
            static::markTestSkipped('No OPENSEARCH configured');
        }

        $this->productRepository = static::getContainer()->get('product.repository');
        $this->searcher = static::getContainer()->get(AdminSearcher::class);

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM product');

        $this->clearElasticsearch();
    }

    public function testNonNumericSearchStillWorks(): void
    {
        $ids = new IdsCollection();
        $productLaptopId = $ids->get('TEST-LAPTOP');

        $products = [
            (new ProductBuilder($ids, 'TEST-LAPTOP', 10))
                ->name('Laptop Computer')
                ->price(100)
                ->build(),
        ];

        $this->productRepository->create($products, Context::createDefaultContext());

        $this->indexElasticSearch(['--only' => ['product']]);
        $this->refreshIndex();

        $results = $this->searcher->search('laptop', ['product'], Context::createDefaultContext());

        static::assertNotEmpty($results);
        static::assertArrayHasKey('product', $results);
        static::assertGreaterThan(0, $results['product']['total']);

        static::assertInstanceOf(ProductCollection::class, $results['product']['data']);
        $foundProductIds = $results['product']['data']->getIds();
        static::assertContains($productLaptopId, $foundProductIds, 'Laptop should be found when searching for "laptop"');
    }

    public function testNumericSearchFindsSubstringMatches(): void
    {
        $ids = new IdsCollection();
        $productX3800Id = $ids->get('TEST-X3800');
        $productABC3800XYZId = $ids->get('TEST-ABC3800XYZ');
        $product3800Id = $ids->get('TEST-3800');

        $products = [
            (new ProductBuilder($ids, 'TEST-X3800', 10))
                ->name('Product X38000')
                ->price(100)
                ->build(),
            (new ProductBuilder($ids, 'TEST-ABC3800XYZ', 10))
                ->name('Product ABC38000XYZ')
                ->price(100)
                ->build(),
            (new ProductBuilder($ids, 'TEST-3800', 10))
                ->name('Product 38000')
                ->price(100)
                ->build(),
        ];

        $this->productRepository->create($products, Context::createDefaultContext());

        $this->indexElasticSearch(['--only' => ['product']]);
        $this->refreshIndex();

        $results = $this->searcher->search('38000', ['product'], Context::createDefaultContext());

        static::assertNotEmpty($results);
        static::assertArrayHasKey('product', $results);
        static::assertGreaterThanOrEqual(3, $results['product']['total'], 'Should find at least 3 products containing "3800"');

        static::assertInstanceOf(ProductCollection::class, $results['product']['data']);
        $foundProductIds = $results['product']['data']->getIds();

        static::assertContains($productX3800Id, $foundProductIds, 'Product X3800 should be found');
        static::assertContains($productABC3800XYZId, $foundProductIds, 'Product ABC3800XYZ should be found');
        static::assertContains($product3800Id, $foundProductIds, 'Product 3800 should be found');
    }

    public function testNumericSearchDoesNotMatchSimilarNumbers(): void
    {
        $ids = new IdsCollection();
        $product3800Id = $ids->get('TEST-3800');
        $product3000Id = $ids->get('TEST-3000');
        $product3801Id = $ids->get('TEST-3801');

        $products = [
            (new ProductBuilder($ids, 'TEST-3800', 10))
                ->name('Product 3800')
                ->price(100)
                ->build(),
            (new ProductBuilder($ids, 'TEST-3000', 10))
                ->name('Product 3000')
                ->price(100)
                ->build(),
            (new ProductBuilder($ids, 'TEST-3801', 10))
                ->name('Product 3801')
                ->price(100)
                ->build(),
        ];

        $this->productRepository->create($products, Context::createDefaultContext());

        $this->indexElasticSearch(['--only' => ['product']]);
        $this->refreshIndex();

        $results = $this->searcher->search('3800', ['product'], Context::createDefaultContext());

        static::assertNotEmpty($results);
        static::assertArrayHasKey('product', $results);

        static::assertInstanceOf(ProductCollection::class, $results['product']['data']);
        $foundProductIds = $results['product']['data']->getIds();

        static::assertContains($product3800Id, $foundProductIds, 'Product 3800 should be found');

        static::assertNotContains($product3000Id, $foundProductIds, 'Product 3000 should NOT be found (no fuzziness)');

        static::assertNotContains($product3801Id, $foundProductIds, 'Product 3801 should NOT be found (different number)');
    }

    /**
     * Regression guard for #15828: a full GTIN-13 EAN search must rank the
     * owning product first, even with an adversarial decoy whose name shares
     * trigrams with the EAN. The "Cable 4572324423420" decoy reproduces the
     * original failure shape.
     */
    public function testExactEanSearchRanksOwnerAboveTrigramOverlap(): void
    {
        $ids = new IdsCollection();
        $ean = '4572324423421';
        $ownerId = $ids->get('OWNER');

        $owner = (new ProductBuilder($ids, 'OWNER', 10))
            ->name('Genuine Item')
            ->price(100)
            ->build();
        $owner['ean'] = $ean;

        $products = [
            $owner,
            // Adversarial: name contains a digit string sharing nearly every
            // trigram with the searched EAN. The fix must isolate the owner
            // from this overlap.
            (new ProductBuilder($ids, 'DECOY-CABLE', 10))
                ->name('Cable 4572324423420')
                ->price(100)
                ->build(),
            (new ProductBuilder($ids, 'DECOY-1', 10))
                ->name('Wireless Headphones')
                ->price(100)
                ->build(),
            (new ProductBuilder($ids, 'DECOY-2', 10))
                ->name('Garden Hose 25ft')
                ->price(100)
                ->build(),
            (new ProductBuilder($ids, 'DECOY-3', 10))
                ->name('Office Chair Ergonomic')
                ->price(100)
                ->build(),
        ];

        $this->productRepository->create($products, Context::createDefaultContext());

        $this->indexElasticSearch(['--only' => ['product']]);
        $this->refreshIndex();

        $hits = $this->runRawAdminSearch($ean);

        static::assertNotEmpty($hits, 'Search must return hits for the exact EAN.');
        static::assertSame(
            $ownerId,
            $hits[0]['_source']['id'] ?? null,
            \sprintf(
                'Expected the EAN-owning product to rank first, got "%s". Hit order: %s',
                $hits[0]['_source']['id'] ?? 'null',
                json_encode(array_column(array_column($hits, '_source'), 'id'), \JSON_THROW_ON_ERROR)
            )
        );
    }

    /**
     * Whole-word autocomplete: "shirt" must find "T-Shirt" via the
     * word-delimiter token split on the `completion` main field, even though
     * the 5-char query is longer than the ngram subfield's max_gram.
     */
    public function testWholeWordAutocompleteFindsHyphenatedNames(): void
    {
        $ids = new IdsCollection();
        $shirtId = $ids->get('SHIRT');

        $products = [
            (new ProductBuilder($ids, 'SHIRT', 10))
                ->name('T-Shirt')
                ->price(100)
                ->build(),
            (new ProductBuilder($ids, 'PANTS', 10))
                ->name('Pants')
                ->price(100)
                ->build(),
        ];

        $this->productRepository->create($products, Context::createDefaultContext());

        $this->indexElasticSearch(['--only' => ['product']]);
        $this->refreshIndex();

        $hits = $this->runRawAdminSearch('shirt');

        static::assertNotEmpty($hits, '"shirt" should find products whose names contain the word — including hyphenated forms like "T-Shirt".');
        $foundIds = array_column(array_column($hits, '_source'), 'id');
        static::assertContains(
            $shirtId,
            $foundIds,
            \sprintf('"T-Shirt" must be found by the whole-word "shirt" query. Hit order: %s', json_encode($foundIds, \JSON_THROW_ON_ERROR))
        );
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    /**
     * Replay `AdminSearcher::buildSearchPayload`'s query against the raw
     * OpenSearch client to read `hits.hits` in score order — the public
     * `AdminSearcher::search` loses ranking at DAL hydration time.
     *
     * @return list<array<string, mixed>>
     */
    private function runRawAdminSearch(string $term): array
    {
        $registry = static::getContainer()->get(AdminSearchRegistry::class);
        $indexer = $registry->getIndexer('product');

        $search = new Search();
        $splitTerms = explode(' ', $term);
        $lastPart = end($splitTerms);

        $search->addQuery(
            new MatchQuery('completion', $term, ['boost' => SearchRanking::HIGH_SEARCH_RANKING]),
            BoolQuery::SHOULD
        );
        $search->addQuery(
            new MatchQuery('completion.ngram', $term, ['boost' => SearchRanking::MIDDLE_SEARCH_RANKING]),
            BoolQuery::SHOULD
        );

        $prefixTerm = preg_match('/^[\p{L}0-9]+$/u', $lastPart) ? $term . '*' : $term;
        $search->addQuery(
            new SimpleQueryStringQuery($prefixTerm, ['fields' => ['text'], 'lenient' => true]),
            BoolQuery::SHOULD
        );

        $query = $indexer->globalCriteria($term, $search);

        $response = static::getContainer()->get(Client::class)->search([
            'index' => static::getContainer()->get(AdminElasticsearchHelper::class)->getIndex($indexer->getName()),
            'body' => $query->toArray(),
        ]);

        return $response['hits']['hits'] ?? [];
    }
}
