<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;

/**
 * @internal
 */
#[CoversClass(ScoreQuery::class)]
class ScoreQueryTest extends TestCase
{
    public function testJsonSerialization(): void
    {
        $scoreQuery = new ScoreQuery(new ContainsFilter('productNumber', '123456'), 100);

        /**
         * @see \Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator::getCriteriaHash
         */
        $json = json_encode($scoreQuery, \JSON_THROW_ON_ERROR);

        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('score', $decoded);
        static::assertSame(100, $decoded['score']);
        static::assertArrayHasKey('query', $decoded);
        static::assertArrayHasKey('scoreField', $decoded);
        static::assertSame('productNumber', $decoded['query']['field']);
        static::assertSame('123456', $decoded['query']['value']);
    }
}
