<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\DependencyInjection\CompilerPass\SalesChannelEntityCompilerPass;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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
    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }

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
