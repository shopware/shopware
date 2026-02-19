<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[CoversClass(EntityCompilerPass::class)]
class EntityCompilerPassTest extends TestCase
{
    public function testEntityRepositoryAutowiring(): void
    {
        $container = new ContainerBuilder();

        $container->register(CustomerAddressDefinition::class, CustomerAddressDefinition::class)
            ->addTag('shopware.entity.definition', ['entity' => CustomerAddressDefinition::ENTITY_NAME]);
        $container->register(CustomerDefinition::class, CustomerDefinition::class)
            ->addTag('shopware.entity.definition', ['entity' => CustomerDefinition::ENTITY_NAME]);

        $container->register(DefinitionInstanceRegistry::class, DefinitionInstanceRegistry::class)
            ->addArgument(new Reference('service_container'))
            ->addArgument([
                CustomerDefinition::ENTITY_NAME => CustomerDefinition::class,
                CustomerAddressDefinition::ENTITY_NAME => CustomerAddressDefinition::class,
            ])
            ->addArgument([
                CustomerDefinition::ENTITY_NAME => 'customer.repository',
                CustomerAddressDefinition::ENTITY_NAME => 'customer_address.repository',
            ]);

        $entityCompilerPass = new EntityCompilerPass();
        $entityCompilerPass->process($container);

        static::assertTrue($container->hasAlias('Shopware\Core\Framework\DataAbstractionLayer\EntityRepository $customerRepository'));
        static::assertTrue($container->hasAlias('Shopware\Core\Framework\DataAbstractionLayer\EntityRepository $customerAddressRepository'));
    }

    public function testEntityRepositoryAutowiringForAlreadyDefinedRepositories(): void
    {
        $container = new ContainerBuilder();

        $container
            ->register(ProductDefinition::class, ProductDefinition::class)
            ->addTag('shopware.entity.definition', ['entity' => ProductDefinition::ENTITY_NAME])
        ;

        $container
            ->register(DefinitionInstanceRegistry::class, DefinitionInstanceRegistry::class)
            ->addArgument(new Reference('service_container'))
            ->addArgument([
                ProductDefinition::ENTITY_NAME => ProductDefinition::class,
            ])
            ->addArgument([
                ProductDefinition::ENTITY_NAME => 'product.repository',
            ])
        ;

        $container
            ->register('product.repository', EntityRepository::class)
            ->addArgument(new Reference(ProductDefinition::class))
        ;

        $entityCompilerPass = new EntityCompilerPass();
        $entityCompilerPass->process($container);

        static::assertTrue($container->hasAlias('Shopware\Core\Framework\DataAbstractionLayer\EntityRepository $productRepository'));
    }

    public function testThrowsOnMissingEntityTagAttribute(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register(ProductDefinition::class, ProductDefinition::class)
            ->addTag('shopware.entity.definition');

        $container
            ->register(DefinitionInstanceRegistry::class, DefinitionInstanceRegistry::class)
            ->addArgument(new Reference('service_container'))
            ->addArgument([])
            ->addArgument([]);

        $this->expectException(DependencyInjectionException::class);
        $this->expectExceptionMessage('missing the required "entity" attribute');

        $entityCompilerPass = new EntityCompilerPass();
        $entityCompilerPass->process($container);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, testThrowsOnMissingEntityTagAttribute covers the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testThrowsOnMissingEntityTagAttributeDeprecated(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register(ProductDefinition::class, ProductDefinition::class)
            ->addTag('shopware.entity.definition');

        $container
            ->register(DefinitionInstanceRegistry::class, DefinitionInstanceRegistry::class)
            ->addArgument(new Reference('service_container'))
            ->addArgument([])
            ->addArgument([]);

        // Deprecation path: falls back to instantiation, resolves entity name
        $entityCompilerPass = new EntityCompilerPass();
        $entityCompilerPass->process($container);

        $definitionRegistry = $container->getDefinition(DefinitionInstanceRegistry::class);
        $entityNameMap = $definitionRegistry->getArgument(1);
        static::assertArrayHasKey('product', $entityNameMap);
        static::assertSame(ProductDefinition::class, $entityNameMap['product']);
    }

    public function testSkipsAttributeEntityDefinitions(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register('test_attribute_entity.definition', AttributeEntityDefinition::class)
            ->addTag('shopware.entity.definition', ['entity' => 'test_attribute_entity'])
        ;
        $container
            ->register(DefinitionInstanceRegistry::class, DefinitionInstanceRegistry::class)
            ->addArgument(new Reference('service_container'))
            ->addArgument([])
            ->addArgument([]);

        $entityCompilerPass = new EntityCompilerPass();
        $entityCompilerPass->process($container);

        static::assertCount(0, $container->getAliases());
    }
}
