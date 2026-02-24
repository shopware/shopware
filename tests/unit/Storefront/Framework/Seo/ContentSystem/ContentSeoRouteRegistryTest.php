<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Storefront\Framework\Seo\ContentSystem\ContentSeoRouteDescriptor;
use Shopware\Storefront\Framework\Seo\ContentSystem\ContentSeoRouteRegistry;

/**
 * @internal
 */
#[CoversClass(ContentSeoRouteRegistry::class)]
class ContentSeoRouteRegistryTest extends TestCase
{
    #[TestDox('finds descriptor by entity type when registered')]
    public function testFindByEntityTypeReturnsDescriptorWhenRegistered(): void
    {
        $definition = $this->createDefinitionStub('product');
        $descriptor = new ContentSeoRouteDescriptor($definition, 'frontend.detail.page');

        $registry = new ContentSeoRouteRegistry([$descriptor]);

        static::assertSame($descriptor, $registry->findByEntityType('product'));
    }

    #[TestDox('returns null when entity type is not registered')]
    public function testFindByEntityTypeReturnsNullWhenNotRegistered(): void
    {
        $definition = $this->createDefinitionStub('product');
        $descriptor = new ContentSeoRouteDescriptor($definition, 'frontend.detail.page');
        $registry = new ContentSeoRouteRegistry([$descriptor]);
        $emptyRegistry = new ContentSeoRouteRegistry([]);

        static::assertNull($registry->findByEntityType('category'));
        static::assertNull($emptyRegistry->findByEntityType('product'));
    }

    #[TestDox('iterates over all registered descriptors')]
    public function testGetIteratorReturnsAllDescriptors(): void
    {
        $descriptor1 = new ContentSeoRouteDescriptor($this->createDefinitionStub('product'), 'frontend.detail.page');
        $descriptor2 = new ContentSeoRouteDescriptor($this->createDefinitionStub('category'), 'frontend.navigation.page');

        $registry = new ContentSeoRouteRegistry([$descriptor1, $descriptor2]);

        $iterated = iterator_to_array($registry->getIterator(), false);

        static::assertCount(2, $iterated);
        static::assertContains($descriptor1, $iterated);
        static::assertContains($descriptor2, $iterated);
    }

    #[TestDox('returns empty iterator when no descriptors are registered')]
    public function testGetIteratorReturnsEmptyIteratorWhenEmpty(): void
    {
        $registry = new ContentSeoRouteRegistry([]);

        $iterated = iterator_to_array($registry->getIterator(), false);

        static::assertSame([], $iterated);
    }

    #[TestDox('indexes descriptors by entity type so last registration wins on duplicate entity type')]
    public function testLastRegisteredDescriptorWinsOnDuplicateEntityType(): void
    {
        $first = new ContentSeoRouteDescriptor($this->createDefinitionStub('product'), 'frontend.detail.page');
        $last = new ContentSeoRouteDescriptor($this->createDefinitionStub('product'), 'frontend.product.page');

        $registry = new ContentSeoRouteRegistry([$first, $last]);

        static::assertSame($last, $registry->findByEntityType('product'));
    }

    private function createDefinitionStub(string $entityType): AbstractContentLayoutAssignableDefinition
    {
        $definition = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $definition->method('getContentLayoutEntityType')->willReturn($entityType);

        return $definition;
    }
}
