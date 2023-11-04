<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator
 */
class EntityCacheKeyGeneratorTest extends TestCase
{
    public function testBuildCmsTag(): void
    {
        static::assertSame('cms-page-foo', EntityCacheKeyGenerator::buildCmsTag('foo'));
    }

    public function testBuildProductTag(): void
    {
        static::assertSame('product-foo', EntityCacheKeyGenerator::buildProductTag('foo'));
    }

    public function testBuildStreamTag(): void
    {
        static::assertSame('product-stream-foo', EntityCacheKeyGenerator::buildStreamTag('foo'));
    }

    /**
     * @dataProvider criteriaHashProvider
     */
    public function testCriteriaHash(Criteria $criteria, string $hash): void
    {
        $generator = new EntityCacheKeyGenerator();

        static::assertSame($hash, $generator->getCriteriaHash($criteria));
    }

    public static function criteriaHashProvider(): \Generator
    {
        yield 'empty' => [
            new Criteria(),
            '749322be59780dc4034598e25b3cd946',
        ];

        yield 'prefix-filter' => [
            (new Criteria())->addFilter(new PrefixFilter('foo', 'bar')),
            'a3def85d7155b475e330761d1eb8b1f1',
        ];

        // this has a different hash because of a different filter type used
        yield 'suffix-filter' => [
            (new Criteria())->addFilter(new SuffixFilter('foo', 'bar')),
            'fa6fcaab1e5a33f0c7fdedb61bef8d22',
        ];

        yield 'filter+sort' => [
            (new Criteria())->addFilter(new PrefixFilter('foo', 'bar'))->addSorting(new FieldSorting('foo')),
            'c5d7faee1a855cfdf7f4a5a8807ec0f0',
        ];

        yield 'filter+sort+sort-desc' => [
            (new Criteria())->addFilter(new PrefixFilter('foo', 'bar'))->addSorting(new FieldSorting('foo', FieldSorting::DESCENDING)),
            'fd5017a9b079d29a790ea9682c11ed74',
        ];

        yield 'filter+agg' => [
            (new Criteria())->addFilter(new PrefixFilter('foo', 'bar'))->addAggregation(new TermsAggregation('foo', 'foo')),
            'c8dcaf7970a7ec0a42e52047f0b60b1a',
        ];
    }
}
