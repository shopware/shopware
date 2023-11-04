<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\ElasticsearchRegistry
 */
class ElasticsearchRegistryTest extends TestCase
{
    public function testRegistery(): void
    {
        $definition = $this->createMock(ElasticsearchProductDefinition::class);
        $definition
            ->method('getEntityDefinition')
            ->willReturn(new ProductDefinition());

        $registry = new ElasticsearchRegistry([
            $definition,
        ]);

        static::assertTrue($registry->has('product'));
        static::assertInstanceOf(ElasticsearchProductDefinition::class, $registry->get('product'));

        static::assertFalse($registry->has('category'));
        static::assertNull($registry->get('category'));
    }
}
