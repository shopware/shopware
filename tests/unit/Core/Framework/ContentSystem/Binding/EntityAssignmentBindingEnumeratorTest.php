<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\ContentSystem\Binding\EntityAssignmentBindingEnumerator;
use Shopware\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(EntityAssignmentBindingEnumerator::class)]
class EntityAssignmentBindingEnumeratorTest extends TestCase
{
    #[TestDox('emits one binding per assigned type that references the layout')]
    public function testEmitsOneBindingPerAssignedType(): void
    {
        $layoutId = Uuid::randomHex();

        $product = $this->assignableDefinition('product_content_layout', 'product');
        $category = $this->assignableDefinition('category_content_layout', 'category');
        $registry = $this->registry([[$product, Uuid::randomHex()], [$category, Uuid::randomHex()]], $layoutId);

        $enumerator = new EntityAssignmentBindingEnumerator($registry, static::createStub(RootContextMapper::class));

        $bindings = $enumerator->enumerate($layoutId, Context::createDefaultContext());

        $sources = array_map(static fn ($binding) => $binding->sourceId, $bindings);
        sort($sources);
        static::assertSame(['category', 'product'], $sources);
    }

    #[TestDox('ignores definitions that are not content-layout assignable')]
    public function testIgnoresNonAssignableDefinitions(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([static::createStub(EntityDefinition::class)]);

        $enumerator = new EntityAssignmentBindingEnumerator($registry, static::createStub(RootContextMapper::class));

        static::assertSame([], $enumerator->enumerate(Uuid::randomHex(), Context::createDefaultContext()));
    }

    #[TestDox('ignores an assignable type that has no row referencing the layout')]
    public function testIgnoresAssignableTypeWithoutAssignment(): void
    {
        $layoutId = Uuid::randomHex();

        $definition = $this->assignableDefinition('category_content_layout', 'category');
        $registry = $this->registry([[$definition, null]], $layoutId);

        $enumerator = new EntityAssignmentBindingEnumerator($registry, static::createStub(RootContextMapper::class));

        static::assertSame([], $enumerator->enumerate($layoutId, Context::createDefaultContext()));
    }

    private function assignableDefinition(string $entityName, string $entityType): AbstractContentLayoutAssignableDefinition
    {
        $definition = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);
        $definition->method('getContentLayoutEntityType')->willReturn($entityType);
        $definition->method('getPageDataRequirements')->willReturn([]);

        return $definition;
    }

    /**
     * @param list<array{0: AbstractContentLayoutAssignableDefinition, 1: string|null}> $assignments definition paired with its first assignment id (null = no row references the layout)
     */
    private function registry(array $assignments, string $contentLayoutId): DefinitionInstanceRegistry
    {
        $definitions = [];
        $repositoriesByEntity = [];

        foreach ($assignments as [$definition, $firstId]) {
            $definitions[] = $definition;

            $repositoriesByEntity[$definition->getEntityName()] = new StaticEntityRepository([
                static function (Criteria $criteria, Context $context) use ($contentLayoutId, $firstId): array {
                    static::assertEquals([new EqualsFilter('contentLayoutId', $contentLayoutId)], $criteria->getFilters());
                    static::assertSame(1, $criteria->getLimit());

                    return $firstId === null ? [] : [$firstId];
                },
            ]);
        }

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn($definitions);
        $registry->method('getRepository')->willReturnCallback(static fn (string $entityName) => $repositoriesByEntity[$entityName]);

        return $registry;
    }
}
