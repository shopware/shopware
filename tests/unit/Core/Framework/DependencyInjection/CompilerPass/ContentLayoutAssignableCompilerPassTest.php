<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Schema\ContentLayoutAssignableEntitySchemaGenerator;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ContentLayoutAssignableCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[CoversClass(ContentLayoutAssignableCompilerPass::class)]
class ContentLayoutAssignableCompilerPassTest extends TestCase
{
    #[TestDox('collects entity types from tagged specification sources')]
    public function testCollectsEntityTypes(): void
    {
        [$container, $resolverDefinition] = $this->createContainerWithResolver();

        $this->addSpecificationSource($container, 'product_source', ProductContentLayoutDefinition::class);
        $this->addSpecificationSource($container, 'category_source', CategoryContentLayoutDefinition::class);

        $pass = new ContentLayoutAssignableCompilerPass();
        $pass->process($container);

        $argument = $resolverDefinition->getArgument(0);
        static::assertIsArray($argument);
        static::assertContains('product', $argument);
        static::assertContains('category', $argument);
    }

    #[TestDox('returns early when resolver definition is missing')]
    public function testReturnsEarlyWithoutResolver(): void
    {
        $container = new ContainerBuilder();

        $pass = new ContentLayoutAssignableCompilerPass();
        $pass->process($container);

        static::assertFalse($container->hasDefinition(ContentLayoutAssignableEntitySchemaGenerator::class));
    }

    #[TestDox('sets empty list when no sources are tagged')]
    public function testSetsEmptyListWhenNoSources(): void
    {
        [$container, $resolverDefinition] = $this->createContainerWithResolver();

        $pass = new ContentLayoutAssignableCompilerPass();
        $pass->process($container);

        $argument = $resolverDefinition->getArgument(0);
        static::assertSame([], $argument);
    }

    #[TestDox('skips tagged service when its class is null')]
    public function testSkipsSourceWithNullClass(): void
    {
        [$container, $resolverDefinition] = $this->createContainerWithResolver();

        $sourceDefinition = new Definition();
        $sourceDefinition->addTag('content_system.entity_specification_source');
        $container->setDefinition('null_class_source', $sourceDefinition);

        $pass = new ContentLayoutAssignableCompilerPass();
        $pass->process($container);

        $argument = $resolverDefinition->getArgument(0);
        static::assertSame([], $argument);
    }

    #[TestDox('throws when tagged service has no assignable definition argument')]
    public function testThrowsWhenNoAssignableDefinitionArgument(): void
    {
        [$container] = $this->createContainerWithResolver();

        $sourceDefinition = new Definition(\stdClass::class);
        $sourceDefinition->setArguments([new Reference('some.other.service')]);
        $sourceDefinition->addTag('content_system.entity_specification_source');
        $container->setDefinition('broken_source', $sourceDefinition);

        $otherService = new Definition(\stdClass::class);
        $container->setDefinition('some.other.service', $otherService);

        $this->expectExceptionObject(
            DependencyInjectionException::missingAssignableDefinition('broken_source', 'content_system.entity_specification_source')
        );

        $pass = new ContentLayoutAssignableCompilerPass();
        $pass->process($container);
    }

    /**
     * @param list<mixed> $sourceArguments
     * @param array<string, Definition> $extraDefinitions
     */
    #[DataProvider('skipsUnresolvableArgumentsProvider')]
    #[TestDox('skips unresolvable arguments and resolves the entity type from a valid later reference')]
    public function testSkipsUnresolvableArguments(array $sourceArguments, array $extraDefinitions): void
    {
        [$container, $resolverDefinition] = $this->createContainerWithResolver();
        $container->setDefinition(ProductContentLayoutDefinition::class, new Definition(ProductContentLayoutDefinition::class));

        foreach ($extraDefinitions as $serviceId => $definition) {
            $container->setDefinition($serviceId, $definition);
        }

        $sourceDefinition = new Definition(\stdClass::class);
        $sourceDefinition->setArguments($sourceArguments);
        $sourceDefinition->addTag('content_system.entity_specification_source');
        $container->setDefinition('product_source', $sourceDefinition);

        $pass = new ContentLayoutAssignableCompilerPass();
        $pass->process($container);

        static::assertSame(['product'], $resolverDefinition->getArgument(0));
    }

    /**
     * @return iterable<string, array{list<mixed>, array<string, Definition>}>
     */
    public static function skipsUnresolvableArgumentsProvider(): iterable
    {
        $validReference = new Reference(ProductContentLayoutDefinition::class);

        yield 'non-reference argument is skipped' => [
            ['plain-string-argument', $validReference],
            [],
        ];

        yield 'reference to an undefined service is skipped' => [
            [new Reference('undefined.service'), $validReference],
            [],
        ];

        yield 'reference to a definition without a class is skipped' => [
            [new Reference('classless.service'), $validReference],
            ['classless.service' => new Definition()],
        ];
    }

    /**
     * @return array{ContainerBuilder, Definition}
     */
    private function createContainerWithResolver(): array
    {
        $container = new ContainerBuilder();
        $resolverDefinition = new Definition(ContentLayoutAssignableEntitySchemaGenerator::class);
        $container->setDefinition(ContentLayoutAssignableEntitySchemaGenerator::class, $resolverDefinition);

        return [$container, $resolverDefinition];
    }

    private function addSpecificationSource(ContainerBuilder $container, string $serviceId, string $definitionClass): void
    {
        $definitionServiceId = $definitionClass;
        $layoutDefinition = new Definition($definitionClass);
        $container->setDefinition($definitionServiceId, $layoutDefinition);

        $sourceDefinition = new Definition(\stdClass::class);
        $sourceDefinition->setArguments([
            new Reference('some.repository'),
            new Reference($definitionServiceId),
            new Reference('some.factory'),
        ]);
        $sourceDefinition->addTag('content_system.entity_specification_source');
        $container->setDefinition($serviceId, $sourceDefinition);

        if (!$container->hasDefinition('some.repository')) {
            $container->setDefinition('some.repository', new Definition(\stdClass::class));
        }
        if (!$container->hasDefinition('some.factory')) {
            $container->setDefinition('some.factory', new Definition(\stdClass::class));
        }
    }
}
