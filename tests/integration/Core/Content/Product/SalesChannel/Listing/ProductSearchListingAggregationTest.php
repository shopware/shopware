<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilder;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Content\Product\SearchKeyword\ScoredMatchesMarker;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
class ProductSearchListingAggregationTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $products = [];
        foreach (['ranked', 'mixed', 'zero-ranked', 'negative-ranked', 'unmatched'] as $key) {
            $products[] = (new ProductBuilder($this->ids, $key))
                ->price(10)
                ->visibility()
                ->manufacturer($key . '-manufacturer')
                ->property($key . '-color', 'color')
                ->build();
        }
        static::getContainer()->get('product.repository')->create($products, Context::createDefaultContext());

        $this->insertKeyword('ranked', 'zorbex', 500);
        $this->insertKeyword('mixed', 'zorbex', 0);
        $this->insertKeyword('mixed', 'zorbexes', 300);
        $this->insertKeyword('zero-ranked', 'zorbex', 0);
        $this->insertKeyword('negative-ranked', 'zorbex', -100);
        $this->insertKeyword('unmatched', 'other', 500);
    }

    public function testKeywordSearchAggregatesWithoutScoreQueriesAndKeepsResultsAndFacets(): void
    {
        $optimized = $this->search(trunkKeywordFilter: false);
        $unoptimized = $this->search(trunkKeywordFilter: true);

        static::assertTrue($this->hasMarkedKeywordFilter($optimized));
        static::assertFalse($this->hasMarkedKeywordFilter($unoptimized));

        static::assertSame(array_values($this->ids->getList(['ranked', 'mixed'])), array_values($optimized->getEntities()->getIds()));
        static::assertSame(array_values($unoptimized->getEntities()->getIds()), array_values($optimized->getEntities()->getIds()));
        static::assertSame($unoptimized->getTotal(), $optimized->getTotal());
        static::assertEquals($this->scores($unoptimized), $this->scores($optimized));

        static::assertEqualsCanonicalizing(
            array_values($this->ids->getList(['ranked-manufacturer', 'mixed-manufacturer'])),
            $this->bucketKeys($optimized, 'manufacturer'),
        );
        static::assertSame($this->bucketKeys($unoptimized, 'manufacturer'), $this->bucketKeys($optimized, 'manufacturer'));

        static::assertEqualsCanonicalizing(
            array_values($this->ids->getList(['ranked-color', 'mixed-color'])),
            $this->bucketKeys($optimized, 'properties'),
        );
        static::assertSame($this->bucketKeys($unoptimized, 'properties'), $this->bucketKeys($optimized, 'properties'));
    }

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    private function search(bool $trunkKeywordFilter): EntitySearchResult
    {
        $pattern = new SearchPattern(new SearchTerm('zorbex'));
        $pattern->addTerm(new SearchTerm('zorbex', 1.2));
        $pattern->addTerm(new SearchTerm('zorbexes', 0.8));

        $interpreter = static::createStub(ProductSearchTermInterpreterInterface::class);
        $interpreter->method('interpret')->willReturn($pattern);

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $criteria = new Criteria();
        $criteria->addAggregation(new TermsAggregation('manufacturer', 'product.manufacturerId'));
        $criteria->addAggregation(new TermsAggregation('properties', 'product.properties.id'));

        (new ProductSearchBuilder($interpreter, new NullLogger(), 100))
            ->build(new Request(['search' => 'zorbex']), $criteria, $context);

        if ($trunkKeywordFilter) {
            $criteria->resetFilters();
            $criteria->addFilter(new AndFilter([
                new EqualsAnyFilter('product.searchKeywords.keyword', array_values($pattern->getAllTerms())),
                new EqualsFilter('product.searchKeywords.languageId', $context->getLanguageId()),
            ]));
        }

        return static::getContainer()->get(ProductListingLoader::class)->load($criteria, $context);
    }

    private function insertKeyword(string $productKey, string $keyword, int $ranking): void
    {
        static::getContainer()->get(Connection::class)->insert('product_search_keyword', [
            'id' => Uuid::randomBytes(),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'product_id' => Uuid::fromHexToBytes($this->ids->get($productKey)),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'keyword' => $keyword,
            'ranking' => $ranking,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @param EntitySearchResult<ProductCollection> $result
     *
     * @return array<string, mixed>
     */
    private function scores(EntitySearchResult $result): array
    {
        $scores = [];
        foreach ($result->getEntities() as $product) {
            $search = $product->getExtension('search');
            static::assertInstanceOf(ArrayEntity::class, $search);

            $scores[$product->getId()] = (float) $search->get('_score');
        }

        return $scores;
    }

    /**
     * @param EntitySearchResult<ProductCollection> $result
     */
    private function hasMarkedKeywordFilter(EntitySearchResult $result): bool
    {
        foreach ($result->getCriteria()->getFilters() as $filter) {
            if ($filter->hasExtension(ScoredMatchesMarker::EXTENSION_NAME)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param EntitySearchResult<ProductCollection> $result
     *
     * @return list<string>
     */
    private function bucketKeys(EntitySearchResult $result, string $aggregation): array
    {
        $terms = $result->getAggregations()->get($aggregation);
        static::assertInstanceOf(TermsResult::class, $terms);

        $keys = $terms->getKeys();
        sort($keys);

        return $keys;
    }
}
