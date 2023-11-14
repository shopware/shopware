<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ScriptIdQuery;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\DataAbstractionLayer\ScriptIdQuery
 */
class ScriptIdQueryTest extends TestCase
{
    #[DisabledFeatures(['v6.6.0.0'])]
    public function testSerializeUsingSource(): void
    {
        $query = new ScriptIdQuery(null, ['bar' => 'baz'], 'foo');

        static::assertSame([
            'script' => [
                'script' => [
                    'source' => 'foo',
                    'lang' => 'painless',
                    'bar' => 'baz',
                ],
            ],
        ], $query->toArray());
    }

    #[DisabledFeatures(['v6.6.0.0'])]
    public function testSerializeUsingId(): void
    {
        $query = new ScriptIdQuery('foo', ['bar' => 'baz'], null);

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
