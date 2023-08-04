<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingProvider;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\Indexing\IndexMappingProvider
 */
class IndexMappingProviderTest extends TestCase
{
    public function testBuild(): void
    {
        $mapping = [
            'foo' => 'bar',
        ];

        $definition = $this->createMock(AbstractElasticsearchDefinition::class);
        $definition->method('getMapping')->willReturn([
            'bar' => 'foo',
        ]);

        $provider = new IndexMappingProvider($mapping);

        static::assertEquals(
            [
                'foo' => 'bar',
                'bar' => 'foo',
            ],
            $provider->build(
                $definition,
                Context::createDefaultContext()
            )
        );
    }
}
