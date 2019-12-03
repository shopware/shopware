<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\ExtensionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedProductManufacturerDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductManufacturerExtension;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;

class EntityExtensionRegisterTest extends TestCase
{
    use KernelTestBehaviour;

    protected function tearDown(): void
    {
        // reboot kernel to create a new container since we manipulated the original one
        KernelLifecycleManager::bootKernel();
        parent::tearDown();
    }

    public function testAddEntityExtensionToEntityWhichAlsoHasSalesChannelDefinition(): void
    {
        $extendedProductDefinition = new ExtendedDummyProductDefinition();
        $this->getContainer()->set(ExtendedDummyProductDefinition::class, $extendedProductDefinition);
        $this->getContainer()->set(
            'sales_channel_definition.' . ExtendedDummyProductDefinition::class,
            $extendedProductDefinition
        );

        $definitionInstanceRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        $this->addToDefinitionRegistry($definitionInstanceRegistry, $extendedProductDefinition);

        $salesChannelDefinitionInstanceRegistry = $this->getContainer()->get(SalesChannelDefinitionInstanceRegistry::class);
        $this->addToDefinitionRegistry($salesChannelDefinitionInstanceRegistry, $extendedProductDefinition);

        $originalExtensionRegistry = $this->getContainer()->get(ExtensionRegistry::class);
        $originalExtensionRegistry->getExtensions();

        $productExtension = new ProductExtension();
        $extensionRegistry = new ExtensionRegistry([$productExtension]);

        $framework = new Framework();

        $registerEntityExtensions = ReflectionHelper::getMethod(Framework::class, 'registerEntityExtensions');
        $registerEntityExtensions->invoke(
            $framework,
            $definitionInstanceRegistry,
            $salesChannelDefinitionInstanceRegistry,
            $extensionRegistry
        );

        $fields = $definitionInstanceRegistry->get(ProductDefinition::class)->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));

        $fields = $salesChannelDefinitionInstanceRegistry->get(ProductDefinition::class)->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));
    }

    public function testAddEntityExtensionToEntityWhichDoesNotHasSalesChannelDefinition(): void
    {
        $extendedProductManufacturerDefinition = new ExtendedProductManufacturerDefinition();
        $this->getContainer()->set(ExtendedProductManufacturerDefinition::class, $extendedProductManufacturerDefinition);
        $this->getContainer()->set(
            'sales_channel_definition.' . ExtendedProductManufacturerDefinition::class,
            $extendedProductManufacturerDefinition
        );

        $definitionInstanceRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        $this->addToDefinitionRegistry($definitionInstanceRegistry, $extendedProductManufacturerDefinition);

        $salesChannelDefinitionInstanceRegistry = $this->getContainer()->get(SalesChannelDefinitionInstanceRegistry::class);
        $this->addToDefinitionRegistry($salesChannelDefinitionInstanceRegistry, $extendedProductManufacturerDefinition);

        $originalExtensionRegistry = $this->getContainer()->get(ExtensionRegistry::class);
        $originalExtensionRegistry->getExtensions();

        $productManufacturerExtension = new ProductManufacturerExtension();
        $extensionRegistry = new ExtensionRegistry([$productManufacturerExtension]);

        $framework = new Framework();

        $registerEntityExtensions = ReflectionHelper::getMethod(Framework::class, 'registerEntityExtensions');
        $registerEntityExtensions->invoke(
            $framework,
            $definitionInstanceRegistry,
            $salesChannelDefinitionInstanceRegistry,
            $extensionRegistry
        );

        $fields = $definitionInstanceRegistry->get(ProductManufacturerDefinition::class)->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));

        $fields = $salesChannelDefinitionInstanceRegistry->get(ProductManufacturerDefinition::class)->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));
    }

    private function addToDefinitionRegistry(
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        EntityDefinition $definition
    ): void {
        $definitionProperty = ReflectionHelper::getProperty(DefinitionInstanceRegistry::class, 'definitions');
        $definitions = $definitionProperty->getValue($definitionInstanceRegistry);
        $definitions[] = $definition;
        $definitionProperty->setValue(
            $definitionInstanceRegistry,
            $definitions
        );
    }
}

class ExtendedDummyProductDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'extended_dummy_product';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new StringField('name', 'name'),

            new FkField('product_id', 'productId', ProductDefinition::class),
            new FkField('language_id', 'languageId', LanguageDefinition::class),

            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),
            new OneToOneAssociationField('toOne', 'product_id', 'id', ProductDefinition::class),
            new ManyToOneAssociationField('manyToOne', 'product_id', ProductDefinition::class, 'id'),
        ]);
    }
}
