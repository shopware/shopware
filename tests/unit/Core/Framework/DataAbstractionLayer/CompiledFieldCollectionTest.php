<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\CompiledFieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CompiledFieldCollection::class)]
class CompiledFieldCollectionTest extends TestCase
{
    public function testAddNewFieldCompilesAndMapsTheField(): void
    {
        $collection = $this->createCollection();

        $field = (new StringField('short_name', 'shortName'))->addFlags(new ApiAware());
        $collection->addNewField($field);

        static::assertSame($field, $collection->get('shortName'));
        static::assertSame($field, $collection->getByStorageName('short_name'));
        static::assertNull($collection->get('unknown'));
        static::assertNull($collection->getByStorageName('unknown'));
    }

    public function testAddRejectsUncompiledFields(): void
    {
        $collection = $this->createCollection();

        $this->expectException(\BadMethodCallException::class);

        $collection->add(new StringField('short_name', 'shortName'));
    }

    public function testRemoveDropsTheStorageMapping(): void
    {
        $collection = $this->createCollection();
        $collection->addNewField(new StringField('short_name', 'shortName'));

        $collection->remove('short_name');

        static::assertNull($collection->getByStorageName('short_name'));
    }

    public function testFilterByFlagKeepsOnlyFlaggedFields(): void
    {
        $collection = $this->createCollection();
        $collection->addNewField((new StringField('short_name', 'shortName'))->addFlags(new Required()));
        $collection->addNewField(new BoolField('active', 'active'));

        $filtered = $collection->filterByFlag(Required::class);

        static::assertCount(1, $filtered);
        static::assertNotNull($filtered->get('shortName'));
    }

    public function testBasicFieldsAndTranslatedAndExtensionFieldsDefaults(): void
    {
        $collection = $this->createCollection();
        $collection->addNewField(new StringField('short_name', 'shortName'));

        static::assertCount(1, $collection->getBasicFields());
        static::assertSame([], $collection->getTranslatedFields());
        static::assertSame([], $collection->getExtensionFields());
        static::assertNull($collection->getChildrenAssociationField());
    }

    public function testGetApiAlias(): void
    {
        static::assertSame('dal_compiled_field_collection', $this->createCollection()->getApiAlias());
    }

    private function createCollection(): CompiledFieldCollection
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        return new CompiledFieldCollection($registry);
    }
}
