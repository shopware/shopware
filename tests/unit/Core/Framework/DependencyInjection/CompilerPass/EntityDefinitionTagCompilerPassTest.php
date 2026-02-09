<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\EntityDefinitionTagCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(EntityDefinitionTagCompilerPass::class)]
class EntityDefinitionTagCompilerPassTest extends TestCase
{
    public function testWritesEntityNameToTagForClassBasedDefinition(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register(ProductDefinition::class, ProductDefinition::class)
            ->addTag('shopware.entity.definition');

        $pass = new EntityDefinitionTagCompilerPass();
        $pass->process($container);

        $tags = $container->getDefinition(ProductDefinition::class)->getTag('shopware.entity.definition');
        static::assertSame('product', $tags[0]['entity']);
    }

    public function testThrowsOnEntityTagMismatch(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register(ProductDefinition::class, ProductDefinition::class)
            ->addTag('shopware.entity.definition', ['entity' => 'wrong_name']);

        $this->expectException(DependencyInjectionException::class);
        $this->expectExceptionMessage('entity="wrong_name", but getEntityName() returns "product"');

        $pass = new EntityDefinitionTagCompilerPass();
        $pass->process($container);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, testThrowsOnEntityTagMismatch covers the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testThrowsOnEntityTagMismatchDeprecated(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register(ProductDefinition::class, ProductDefinition::class)
            ->addTag('shopware.entity.definition', ['entity' => 'wrong_name']);

        $pass = new EntityDefinitionTagCompilerPass();
        $pass->process($container);

        // Deprecation path: tag is kept as-is, no exception thrown
        $tags = $container->getDefinition(ProductDefinition::class)->getTag('shopware.entity.definition');
        static::assertSame('wrong_name', $tags[0]['entity']);
    }

    public function testSkipsDefinitionWithNullClass(): void
    {
        $container = new ContainerBuilder();
        $container->register('test.definition')
            ->addTag('shopware.entity.definition');

        $pass = new EntityDefinitionTagCompilerPass();
        $pass->process($container);

        $tags = $container->getDefinition('test.definition')->getTag('shopware.entity.definition');
        static::assertSame([], $tags[0]);
    }

    public function testSkipsNonEntityDefinitionSubclass(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register('test.service', \stdClass::class)
            ->addTag('shopware.entity.definition');

        $pass = new EntityDefinitionTagCompilerPass();
        $pass->process($container);

        $tags = $container->getDefinition('test.service')->getTag('shopware.entity.definition');
        static::assertSame([], $tags[0]);
    }

    /**
     * @param list<array<string, string>> $inputTags
     * @param list<array<string, string>> $expectedTags
     */
    #[DataProvider('tagPreservationProvider')]
    public function testPreservesExistingTagAttributes(array $inputTags, array $expectedTags): void
    {
        $container = new ContainerBuilder();
        $definition = $container->register(ProductDefinition::class, ProductDefinition::class);

        foreach ($inputTags as $attributes) {
            $definition->addTag('shopware.entity.definition', $attributes);
        }

        $pass = new EntityDefinitionTagCompilerPass();
        $pass->process($container);

        $tags = $container->getDefinition(ProductDefinition::class)->getTag('shopware.entity.definition');
        static::assertSame($expectedTags, $tags);
    }

    /**
     * @return iterable<string, array{inputTags: list<array<string, string>>, expectedTags: list<array<string, string>>}>
     */
    public static function tagPreservationProvider(): iterable
    {
        yield 'adds entity to tag without erasing other attributes' => [
            'inputTags' => [['custom' => 'value']],
            'expectedTags' => [['custom' => 'value', 'entity' => 'product']],
        ];

        yield 'preserves multiple tag entries' => [
            'inputTags' => [['custom' => 'first'], ['custom' => 'second']],
            'expectedTags' => [['custom' => 'first', 'entity' => 'product'], ['custom' => 'second', 'entity' => 'product']],
        ];

        yield 'does not overwrite existing entity attribute' => [
            'inputTags' => [['entity' => 'product', 'custom' => 'value']],
            'expectedTags' => [['entity' => 'product', 'custom' => 'value']],
        ];

        yield 'skips service when first entry already has entity' => [
            'inputTags' => [['entity' => 'product'], ['custom' => 'value']],
            'expectedTags' => [['entity' => 'product'], ['custom' => 'value']],
        ];

        yield 'treats empty string entity as missing and resolves it' => [
            'inputTags' => [['entity' => '']],
            'expectedTags' => [['entity' => 'product']],
        ];
    }

    public function testHandlesSalesChannelDefinitionTag(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register(ProductDefinition::class, ProductDefinition::class)
            ->addTag('shopware.sales_channel.entity.definition');

        $pass = new EntityDefinitionTagCompilerPass();
        $pass->process($container);

        $tags = $container->getDefinition(ProductDefinition::class)->getTag('shopware.sales_channel.entity.definition');
        static::assertSame('product', $tags[0]['entity']);
    }

    public function testSkipsValidationForDefinitionWithConstructorParameters(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register(ConstructorParamDefinition::class, ConstructorParamDefinition::class)
            ->addTag('shopware.entity.definition', ['entity' => 'any_name_is_accepted']);

        $pass = new EntityDefinitionTagCompilerPass();
        $pass->process($container);

        $tags = $container->getDefinition(ConstructorParamDefinition::class)->getTag('shopware.entity.definition');
        static::assertSame('any_name_is_accepted', $tags[0]['entity']);
    }

    public function testThrowsUnresolvableForDefinitionWithConstructorParametersAndNoTag(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register(ConstructorParamDefinition::class, ConstructorParamDefinition::class)
            ->addTag('shopware.entity.definition');

        $this->expectException(DependencyInjectionException::class);
        $this->expectExceptionMessage('could not be resolved');

        $pass = new EntityDefinitionTagCompilerPass();
        $pass->process($container);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, testThrowsUnresolvableForDefinitionWithConstructorParametersAndNoTag covers the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testThrowsUnresolvableForDefinitionWithConstructorParametersAndNoTagDeprecated(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register(ConstructorParamDefinition::class, ConstructorParamDefinition::class)
            ->addTag('shopware.entity.definition');

        $pass = new EntityDefinitionTagCompilerPass();
        $pass->process($container);

        // Deprecation path: service is skipped, tag stays without entity attribute
        $tags = $container->getDefinition(ConstructorParamDefinition::class)->getTag('shopware.entity.definition');
        static::assertSame([], $tags[0]);
    }

    public function testSkipsValidationForDefinitionWithPrivateConstructor(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register(PrivateConstructorDefinition::class, PrivateConstructorDefinition::class)
            ->addTag('shopware.entity.definition', ['entity' => 'any_name_is_accepted']);

        $pass = new EntityDefinitionTagCompilerPass();
        $pass->process($container);

        $tags = $container->getDefinition(PrivateConstructorDefinition::class)->getTag('shopware.entity.definition');
        static::assertSame('any_name_is_accepted', $tags[0]['entity']);
    }

    public function testThrowsUnresolvableForDefinitionWithPrivateConstructor(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register(PrivateConstructorDefinition::class, PrivateConstructorDefinition::class)
            ->addTag('shopware.entity.definition');

        $this->expectException(DependencyInjectionException::class);
        $this->expectExceptionMessage('could not be resolved');

        $pass = new EntityDefinitionTagCompilerPass();
        $pass->process($container);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, testThrowsUnresolvableForDefinitionWithPrivateConstructor covers the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testThrowsUnresolvableForDefinitionWithPrivateConstructorDeprecated(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register(PrivateConstructorDefinition::class, PrivateConstructorDefinition::class)
            ->addTag('shopware.entity.definition');

        $pass = new EntityDefinitionTagCompilerPass();
        $pass->process($container);

        // Deprecation path: service is skipped, tag stays without entity attribute
        $tags = $container->getDefinition(PrivateConstructorDefinition::class)->getTag('shopware.entity.definition');
        static::assertSame([], $tags[0]);
    }
}

/**
 * @internal
 */
class ConstructorParamDefinition extends EntityDefinition
{
    /**
     * @param array<string, mixed> $meta
     *
     * @phpstan-ignore constructor.unusedParameter
     */
    public function __construct(array $meta = [])
    {
        parent::__construct();
    }

    public function getEntityName(): string
    {
        return 'constructor_param';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection();
    }
}

/**
 * @internal
 */
class PrivateConstructorDefinition extends EntityDefinition
{
    /**
     * @phpstan-ignore symplify.parentMethodVisibilityOverride
     */
    private function __construct()
    {
        parent::__construct();
    }

    public function getEntityName(): string
    {
        return 'private_constructor';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection();
    }
}
