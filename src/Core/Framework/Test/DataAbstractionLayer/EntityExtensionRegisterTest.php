<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedProductDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedProductManufacturerDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductManufacturerExtension;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;

/**
 * @internal
 *
 * @group skip-paratest
 */
class EntityExtensionRegisterTest extends TestCase
{
    use KernelTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    protected function tearDown(): void
    {
        // reboot kernel to create a new container since we manipulated the original one
        KernelLifecycleManager::bootKernel();
        parent::tearDown();
    }

    public function testAddEntityExtensionToEntityWhichAlsoHasSalesChannelDefinition(): void
    {
        $this->registerDefinition(ExtendedProductDefinition::class);
        $this->registerDefinitionWithExtensions(ProductDefinition::class, ProductExtension::class);

        $fields = $this->getContainer()
            ->get(DefinitionInstanceRegistry::class)
            ->get(ProductDefinition::class)
            ->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));

        $this->registerSalesChannelDefinition(ExtendedProductDefinition::class);
        $this->registerSalesChannelDefinitionWithExtensions(ProductDefinition::class, ProductExtension::class);
        $fields = $this->getContainer()
            ->get(SalesChannelDefinitionInstanceRegistry::class)
            ->get(ProductDefinition::class)
            ->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));

        $this->removeExtension(ProductExtension::class);
    }

    public function testAddEntityExtensionToEntityWhichDoesNotHasSalesChannelDefinition(): void
    {
        $this->registerDefinition(ExtendedProductManufacturerDefinition::class);
        $this->registerDefinitionWithExtensions(ProductManufacturerDefinition::class, ProductManufacturerExtension::class);

        $fields = $this->getContainer()
            ->get(DefinitionInstanceRegistry::class)
            ->get(ProductManufacturerDefinition::class)
            ->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));

        $this->registerSalesChannelDefinition(ExtendedProductManufacturerDefinition::class);
        $this->registerSalesChannelDefinitionWithExtensions(ProductManufacturerDefinition::class, ProductManufacturerExtension::class);
        $fields = $this->getContainer()
            ->get(SalesChannelDefinitionInstanceRegistry::class)
            ->get(ProductManufacturerDefinition::class)
            ->getFields();
        static::assertTrue($fields->has('toOne'));
        static::assertInstanceOf(OneToOneAssociationField::class, $fields->get('toOne'));
        static::assertTrue($fields->has('oneToMany'));
        static::assertInstanceOf(OneToManyAssociationField::class, $fields->get('oneToMany'));

        $this->removeExtension(ProductManufacturerExtension::class);
    }
}
