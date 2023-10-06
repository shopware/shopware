<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ScriptIdQuery;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\DataAbstractionLayer\ScriptIdQuery
 */
class ScriptIdQueryTest extends TestCase
{
    public function testSerialize(): void
    {
        $query = new ScriptIdQuery('foo', ['bar' => 'baz']);

        static::assertSame([
            'script' => [
                'script' => [
                    'id' => 'foo',
                    'bar' => 'baz',
                ],
            ],
        ], $query->toArray());
    }
}
