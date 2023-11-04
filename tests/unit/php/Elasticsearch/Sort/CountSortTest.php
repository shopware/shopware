<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Sort;

use OpenSearchDSL\Sort\FieldSort;
use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Sort\CountSort;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Sort\CountSort
 */
class CountSortTest extends TestCase
{
    public function testSerialize(): void
    {
        $sort = new CountSort('test.test', FieldSort::ASC);

        static::assertEquals(
            [
                'test._count' => [
                    'nested' => [
                        'path' => 'test',
                    ],
                    'missing' => 0,
                    'order' => 'asc',
                    'mode' => 'sum',
                ],
            ],
            $sort->toArray()
        );
    }
}
