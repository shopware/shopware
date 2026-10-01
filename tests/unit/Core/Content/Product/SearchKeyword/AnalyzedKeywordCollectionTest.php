<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SearchKeyword;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SearchKeyword\AnalyzedKeyword;
use Shopware\Core\Content\Product\SearchKeyword\AnalyzedKeywordCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AnalyzedKeywordCollection::class)]
class AnalyzedKeywordCollectionTest extends TestCase
{
    public function testAddKeepsTheHighestRankingPerKeyword(): void
    {
        $collection = new AnalyzedKeywordCollection();
        $collection->add(new AnalyzedKeyword('shoe', 100));
        $collection->add(new AnalyzedKeyword('shoe', 500));
        $collection->add(new AnalyzedKeyword('shoe', 200));

        static::assertCount(1, $collection);
        static::assertSame(500.0, $collection->get('shoe')?->getRanking());
    }

    public function testSetKeysByKeywordAndOverwrites(): void
    {
        $collection = new AnalyzedKeywordCollection();
        $collection->set('ignored', new AnalyzedKeyword('shoe', 300));
        $collection->set('ignored', new AnalyzedKeyword('shoe', 100));

        static::assertCount(1, $collection);
        static::assertSame(100.0, $collection->get('shoe')?->getRanking());
    }

    public function testApiAlias(): void
    {
        static::assertSame('product_search_keyword_analyzed_collection', (new AnalyzedKeywordCollection())->getApiAlias());
    }
}
