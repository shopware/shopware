<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchRegistry::class)]
class ElasticsearchRegistryTest extends TestCase
{
    public function testRegistry(): void
    {
        $definition = static::createStub(ElasticsearchProductDefinition::class);
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

        static::assertSame(['product'], $registry->getDefinitionNames());
    }
}
