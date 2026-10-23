<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDefinition::class)]
class ProductDefinitionTest extends TestCase
{
    private ProductDefinition $definition;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $definition = $registry->getByEntityName('product');
        static::assertInstanceOf(ProductDefinition::class, $definition);

        $this->definition = $definition;
    }

    public function testProductDocumentsAssociationIsWiredToProductDocumentDefinition(): void
    {
        $field = $this->definition->getFields()->get('productDocuments');

        static::assertInstanceOf(OneToManyAssociationField::class, $field);
        static::assertSame(ProductDocumentDefinition::class, $field->getReferenceClass());
        static::assertSame('product_id', $field->getReferenceField());
        static::assertSame('id', $field->getLocalField());
        static::assertTrue($field->is(ApiAware::class));
    }

    public function testProductDocumentsAreInheritedByVariantsAndDeletedWithProduct(): void
    {
        $field = $this->definition->getFields()->get('productDocuments');

        static::assertInstanceOf(OneToManyAssociationField::class, $field);
        static::assertTrue($field->is(Inherited::class));
        static::assertTrue($field->is(CascadeDelete::class));
    }

    public function testSearchFields(): void
    {
        // don't change this list, each additional field will reduce the performance

        $fields = $this->definition->getFields();

        $searchable = $fields->filterByFlag(SearchRanking::class);

        $keys = $searchable->getKeys();

        // NEVER add an association to this list!!! otherwise, the API query takes too long and shops with many products (more than 1000) will fail
        $expected = ['customSearchKeywords', 'productNumber', 'manufacturerNumber', 'ean', 'name'];

        sort($expected);
        sort($keys);

        static::assertSame($expected, $keys);
    }

    public function testDefaultsMakeANewProductAPhysicalSellableItem(): void
    {
        $defaults = $this->getDefinition()->getDefaults();

        static::assertSame(ProductDefinition::TYPE_PHYSICAL, $defaults['type']);
        static::assertFalse($defaults['isCloseout']);
        static::assertTrue($defaults['active']);
        static::assertSame(1, $defaults['minPurchase']);
    }

    public function testChildDefaultsOnlyPresetTheType(): void
    {
        static::assertSame(['type' => ProductDefinition::TYPE_PHYSICAL], $this->getDefinition()->getChildDefaults());
    }

    public function testSince(): void
    {
        static::assertSame('6.0.0.0', $this->getDefinition()->since());
    }

    public function testDescribesItsEntity(): void
    {
        $definition = $this->getDefinition();

        static::assertTrue($definition->isInheritanceAware());
        static::assertSame(ProductCollection::class, $definition->getCollectionClass());
        static::assertSame(ProductEntity::class, $definition->getEntityClass());
        static::assertSame(ProductHydrator::class, $definition->getHydratorClass());
    }

    public function testTypeFieldIsRequiredWithoutALegacyStatesField(): void
    {
        $fields = $this->getDefinition()->getFields();

        static::assertNull($fields->get('states'));

        $type = $fields->get('type');
        static::assertNotNull($type);
        static::assertTrue($type->is(Required::class));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyFieldSetKeepsStatesAndAnOptionalType(): void
    {
        $fields = $this->getDefinition()->getFields();

        static::assertNotNull($fields->get('states'));

        $type = $fields->get('type');
        static::assertNotNull($type);
        static::assertFalse($type->is(Required::class));
    }

    private function getDefinition(): ProductDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class)
        );

        $definition = $registry->getByEntityName('product');
        static::assertInstanceOf(ProductDefinition::class, $definition);

        return $definition;
    }
}
