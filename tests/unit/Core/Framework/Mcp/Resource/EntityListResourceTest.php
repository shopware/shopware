<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Resource\EntityListResource;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityListResource::class)]
class EntityListResourceTest extends TestCase
{
    public function testInvokeReturnsSortedEntitiesWithCorrectStructure(): void
    {
        $defProduct = static::createStub(EntityDefinition::class);
        $defProduct->method('getEntityName')->willReturn('product');
        $defCategory = static::createStub(EntityDefinition::class);
        $defCategory->method('getEntityName')->willReturn('category');

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([$defProduct, $defCategory]);

        $resource = new EntityListResource($registry);
        $result = ($resource)();

        static::assertSame('shopware://entities', $result['uri']);
        static::assertSame('application/json', $result['mimeType']);
        static::assertArrayHasKey('text', $result);

        $entities = json_decode($result['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(['category', 'product'], $entities);
    }
}
