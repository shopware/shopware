<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FilteredBulkEntityExtension;
use Shopware\Core\System\DependencyInjection\CompilerPass\SalesChannelEntityCompilerPass;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(SalesChannelEntityCompilerPass::class)]
class SalesChannelEntityCompilerPassTest extends TestCase
{
    public function testExtensionsGetsAdded(): void
    {
        $container = $this->getContainerBuilder();

        $extension = new Definition(ProductEntityExtension::class);
        $extension->setPublic(true);
        $extension->addTag('shopware.entity.extension');
        $container->setDefinition(ProductEntityExtension::class, $extension);

        $container->compile();

        $definition = $container->get(ProductDefinition::class);
        $definition->compile(new StaticDefinitionInstanceRegistry([], $this->createMock(ValidatorInterface::class), $this->createMock(EntityWriteGateway::class)));

        static::assertTrue($definition->getFields()->has('test'));
        static::assertInstanceOf(StringField::class, $definition->getFields()->get('test'));

        $methodCalls = $container->getDefinition('sales_channel_definition.' . ProductDefinition::class)->getMethodCalls();
        static::assertCount(2, $methodCalls);
        static::assertSame('addExtension', $methodCalls[1][0]);
        static::assertInstanceOf(Reference::class, $methodCalls[1][1][0]);
        static::assertSame(ProductEntityExtension::class, (string) $methodCalls[1][1][0]);
    }

    public function testBulky(): void
    {
        $container = $this->getContainerBuilder();

        $extension = new Definition(BulkyProductExtension::class);
        $extension->setPublic(true);
        $extension->addTag('shopware.bulk.entity.extension');
        $container->setDefinition(BulkyProductExtension::class, $extension);

        $container->compile();

        $definition = $container->get(ProductDefinition::class);
        $definition->compile(new StaticDefinitionInstanceRegistry([], $this->createMock(ValidatorInterface::class), $this->createMock(EntityWriteGateway::class)));

        static::assertTrue($definition->getFields()->has('test'));
        static::assertInstanceOf(StringField::class, $definition->getFields()->get('test'));

        $methodCalls = $container->getDefinition('sales_channel_definition.' . ProductDefinition::class)->getMethodCalls();
        static::assertCount(2, $methodCalls);
        static::assertSame('addExtension', $methodCalls[1][0]);
        static::assertInstanceOf(Definition::class, $methodCalls[1][1][0]);
        static::assertSame(FilteredBulkEntityExtension::class, $methodCalls[1][1][0]->getClass());
    }

    public function testAttributeEntityExtensionGetsAdded(): void
    {
        $container = $this->getContainerBuilder();

        $attributeDefinition = new Definition(AttributeEntityDefinition::class);
        $attributeDefinition->setPublic(true);
        $attributeDefinition->addTag('shopware.entity.definition');
        $attributeDefinition->addArgument([
            'entity_name' => 'test_attribute_entity',
            'fields' => [],
        ]);
        $container->setDefinition('test_attribute_entity.definition', $attributeDefinition);

        $extension = new Definition(AttributeEntityExtension::class);
        $extension->setPublic(true);
        $extension->addTag('shopware.entity.extension');
        $container->setDefinition(AttributeEntityExtension::class, $extension);

        $container->compile();

        static::assertTrue($container->has('test_attribute_entity.definition'));
        $definition = $container->get('test_attribute_entity.definition');
        static::assertInstanceOf(AttributeEntityDefinition::class, $definition);

        $definition->compile(new StaticDefinitionInstanceRegistry([], $this->createMock(ValidatorInterface::class), $this->createMock(EntityWriteGateway::class)));

        static::assertTrue($definition->getFields()->has('product'));
        static::assertInstanceOf(ManyToOneAssociationField::class, $definition->getFields()->get('product'));

        $methodCalls = $container->getDefinition('sales_channel_definition.test_attribute_entity.definition')->getMethodCalls();

        static::assertCount(2, $methodCalls);
        static::assertSame('addExtension', $methodCalls[1][0]);
        static::assertInstanceOf(Reference::class, $methodCalls[1][1][0]);
        static::assertSame(AttributeEntityExtension::class, (string) $methodCalls[1][1][0]);
    }

    public function testAttributeEntityExtensiondWithoutAgruments(): void
    {
        $container = $this->getContainerBuilder();

        $attributeDefinition = new Definition(AttributeEntityDefinition::class);
        $attributeDefinition->setPublic(true);
        $attributeDefinition->addTag('shopware.entity.definition');
        $container->setDefinition('test_attribute_entity.definition', $attributeDefinition);

        $extension = new Definition(AttributeEntityExtension::class);
        $extension->setPublic(true);
        $extension->addTag('shopware.entity.extension');
        $container->setDefinition(AttributeEntityExtension::class, $extension);

        static::expectException(DefinitionNotFoundException::class);
        $container->compile();
    }

    public function getContainerBuilder(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new SalesChannelEntityCompilerPass());
        $definition = new Definition(SalesChannelDefinitionInstanceRegistry::class);
        $definition->setArguments([[], [], [], []]);

        $container->setDefinition(SalesChannelDefinitionInstanceRegistry::class, $definition);

        $productRegular = new Definition(ProductDefinition::class);
        $productRegular->setPublic(true);
        $productRegular->addTag('shopware.entity.definition');
        $container->setDefinition(ProductDefinition::class, $productRegular);

        return $container;
    }
}

/**
 * @internal
 */
class ProductEntityExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new StringField('test', 'test'))->addFlags(new Runtime())
        );
    }

    public function getEntityName(): string
    {
        return 'product';
    }
}

/**
 * @internal
 */
class BulkyProductExtension extends BulkEntityExtension
{
    public function collect(): \Generator
    {
        yield 'product' => [
            (new StringField('test', 'test'))->addFlags(new Runtime()),
        ];
    }
}

/**
 * @internal
 */
class AttributeEntityExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class));
    }

    public function getEntityName(): string
    {
        return 'test_attribute_entity';
    }
}
