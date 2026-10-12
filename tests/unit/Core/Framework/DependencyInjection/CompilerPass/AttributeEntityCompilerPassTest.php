<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\DalSearchInstrumentor;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\AttributeEntityCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\DependencyInjection\CompilerPass\SalesChannelEntityCompilerPass;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AttributeEntityCompilerPass::class)]
class AttributeEntityCompilerPassTest extends TestCase
{
    public function testAttributeEntityDefinitionHasTag(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(DefinitionInstanceRegistry::class, new Definition(DefinitionInstanceRegistry::class));

        $attributeEntity = new Definition(TestAttributeEntity::class);
        $attributeEntity->setPublic(true);
        $attributeEntity->addTag('shopware.entity');
        $container->setDefinition(TestAttributeEntity::class, $attributeEntity);

        $compiler = new AttributeEntityCompiler();

        $compilerPass = new AttributeEntityCompilerPass($compiler);
        $compilerPass->process($container);

        static::assertTrue($container->hasDefinition('test_attribute_entity.definition'));
        static::assertTrue($container->getDefinition('test_attribute_entity.definition')->hasTag('shopware.entity.definition'));

        static::assertTrue($container->hasDefinition('test_attribute_entity_translation.definition'));
        static::assertTrue($container->getDefinition('test_attribute_entity_translation.definition')->hasTag('shopware.entity.definition'));

        static::assertTrue($container->hasDefinition('customer_test_attribute_entity.definition'));
        static::assertTrue($container->getDefinition('customer_test_attribute_entity.definition')->hasTag('shopware.entity.definition'));
    }

    public function testAssociationsKeepResolvingThroughTheDalRegistryOnceTheSalesChannelRegistryIsBuilt(): void
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AttributeEntityCompilerPass(new AttributeEntityCompiler()), PassConfig::TYPE_BEFORE_OPTIMIZATION, 99);
        $container->addCompilerPass(new EntityCompilerPass());
        $container->addCompilerPass(new SalesChannelEntityCompilerPass());

        $container->setDefinition(DefinitionInstanceRegistry::class, new Definition(DefinitionInstanceRegistry::class, [new Reference('service_container'), [], []]))->setPublic(true);
        $container->setDefinition(SalesChannelDefinitionInstanceRegistry::class, new Definition(SalesChannelDefinitionInstanceRegistry::class, ['', new Reference('service_container'), [], []]))->setPublic(true);

        foreach ([EntityReaderInterface::class, VersionManager::class, EntitySearcherInterface::class, EntityAggregatorInterface::class, 'event_dispatcher', EntityLoadedEventFactory::class, DalSearchInstrumentor::class] as $repositoryDependency) {
            $container->register($repositoryDependency)->setSynthetic(true)->setPublic(true);
        }

        $container->setDefinition(CustomerDefinition::class, new Definition(CustomerDefinition::class))->addTag('shopware.entity.definition');
        $container->setDefinition(TestAttributeEntity::class, new Definition(TestAttributeEntity::class))->setPublic(true)->addTag('shopware.entity');

        $container->compile();

        $registry = $container->get(DefinitionInstanceRegistry::class);
        static::assertInstanceOf(DefinitionInstanceRegistry::class, $registry);
        $container->get(SalesChannelDefinitionInstanceRegistry::class);

        $customers = $registry->getByEntityName('test_attribute_entity')->getFields()->get('customers');
        static::assertInstanceOf(ManyToManyAssociationField::class, $customers);
        static::assertSame($registry->getByEntityName('customer'), $customers->getToManyReferenceDefinition());
    }
}

/**
 * @internal
 */
#[Entity('test_attribute_entity')]
class TestAttributeEntity extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Required]
    #[Field(type: FieldType::STRING, translated: true)]
    public string $name;

    /**
     * @var array<string, ArrayEntity>|null
     */
    #[Translations]
    public ?array $translations = null;

    /**
     * @var array<string, CustomerEntity>|null
     */
    #[ManyToMany(entity: 'customer', onDelete: OnDelete::SET_NULL)]
    public ?array $customers = null;
}
