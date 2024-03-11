<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BreadcrumbField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CashRoundingConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CronIntervalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateIntervalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TaxFreeConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeBreadcrumbField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VariantListingConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionDataPayloadField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(SchemaBuilder::class)]
class SchemaBuilderTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [
                TestEntityWithSkippedFieldsDefinition::class,
                TestAssociationDefinition::class,
                TestEntityWithAllPossibleFieldsDefinition::class,
                TestEntityWithForeignKeysDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    public function testSkipsCertainFields(): void
    {
        $definition = $this->registry->get(TestEntityWithSkippedFieldsDefinition::class);

        $schemaBuilder = new SchemaBuilder();

        $table = $schemaBuilder->buildSchemaOfDefinition($definition);

        static::assertCount(4, $table->getColumns());

        static::assertSame('id', $table->getPrimaryKey()?->getColumns()[0]);

        static::assertArrayHasKey('id', $table->getColumns());
        static::assertArrayHasKey('relation_id', $table->getColumns());

        static::assertArrayNotHasKey('runtime', $table->getColumns());
        static::assertArrayNotHasKey('translated', $table->getColumns());
    }

    public function testDifferentFieldTypes(): void
    {
        $definition = $this->registry->get(TestEntityWithAllPossibleFieldsDefinition::class);

        $schemaBuilder = new SchemaBuilder();

        $table = $schemaBuilder->buildSchemaOfDefinition($definition);

        static::assertSame('id', $table->getPrimaryKey()?->getColumns()[0]);

        static::assertArrayHasKey('id', $table->getColumns());
        static::assertEquals(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumns()['id']->getType()));

        static::assertArrayHasKey('version_id', $table->getColumns());
        static::assertEquals(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumns()['version_id']->getType()));

        static::assertArrayHasKey('created_by_id', $table->getColumns());
        static::assertEquals(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumns()['created_by_id']->getType()));

        static::assertArrayHasKey('updated_by_id', $table->getColumns());
        static::assertEquals(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumns()['updated_by_id']->getType()));

        static::assertArrayHasKey('state_id', $table->getColumns());
        static::assertEquals(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumns()['state_id']->getType()));

        static::assertArrayHasKey('created_at', $table->getColumns());
        static::assertEquals(Types::DATETIME_MUTABLE, Type::getTypeRegistry()->lookupName($table->getColumns()['created_at']->getType()));

        static::assertArrayHasKey('updated_at', $table->getColumns());
        static::assertEquals(Types::DATETIME_MUTABLE, Type::getTypeRegistry()->lookupName($table->getColumns()['updated_at']->getType()));

        static::assertArrayHasKey('datetime', $table->getColumns());
        static::assertEquals(Types::DATETIME_MUTABLE, Type::getTypeRegistry()->lookupName($table->getColumns()['datetime']->getType()));

        static::assertArrayHasKey('date', $table->getColumns());
        static::assertEquals(Types::DATE_MUTABLE, Type::getTypeRegistry()->lookupName($table->getColumns()['date']->getType()));

        static::assertArrayHasKey('cart_price', $table->getColumns());
        static::assertEquals(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumns()['cart_price']->getType()));

        static::assertArrayHasKey('calculated_price', $table->getColumns());
        static::assertEquals(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumns()['calculated_price']->getType()));

        static::assertArrayHasKey('price', $table->getColumns());
        static::assertEquals(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumns()['price']->getType()));

        static::assertArrayHasKey('price_definition', $table->getColumns());
        static::assertEquals(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumns()['price_definition']->getType()));

        static::assertArrayHasKey('json', $table->getColumns());
        static::assertEquals(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumns()['json']->getType()));

        static::assertArrayHasKey('list', $table->getColumns());
        static::assertEquals(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumns()['list']->getType()));

        static::assertArrayHasKey('config_json', $table->getColumns());
        static::assertEquals(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumns()['config_json']->getType()));

        static::assertArrayHasKey('custom_fields', $table->getColumns());
        static::assertEquals(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumns()['custom_fields']->getType()));

        static::assertArrayHasKey('breadcrumb', $table->getColumns());
        static::assertEquals(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumns()['breadcrumb']->getType()));

        static::assertArrayHasKey('cash_rounding_config', $table->getColumns());
        static::assertEquals(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumns()['cash_rounding_config']->getType()));

        static::assertArrayHasKey('object', $table->getColumns());
        static::assertEquals(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumns()['object']->getType()));

        static::assertArrayHasKey('tax_free_config', $table->getColumns());
        static::assertEquals(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumns()['tax_free_config']->getType()));

        static::assertArrayHasKey('tree_breadcrumb', $table->getColumns());
        static::assertEquals(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumns()['tree_breadcrumb']->getType()));

        static::assertArrayHasKey('variant_listing_config', $table->getColumns());
        static::assertEquals(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumns()['variant_listing_config']->getType()));

        static::assertArrayHasKey('version_data_payload', $table->getColumns());
        static::assertEquals(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumns()['version_data_payload']->getType()));

        static::assertArrayHasKey('child_count', $table->getColumns());
        static::assertEquals(Types::INTEGER, Type::getTypeRegistry()->lookupName($table->getColumns()['child_count']->getType()));

        static::assertArrayHasKey('auto_increment', $table->getColumns());
        static::assertEquals(Types::INTEGER, Type::getTypeRegistry()->lookupName($table->getColumns()['auto_increment']->getType()));

        static::assertArrayHasKey('int', $table->getColumns());
        static::assertEquals(Types::INTEGER, Type::getTypeRegistry()->lookupName($table->getColumns()['int']->getType()));

        static::assertArrayHasKey('auto_increment', $table->getColumns());
        static::assertEquals(Types::INTEGER, Type::getTypeRegistry()->lookupName($table->getColumns()['auto_increment']->getType()));

        static::assertArrayHasKey('tree_level', $table->getColumns());
        static::assertEquals(Types::INTEGER, Type::getTypeRegistry()->lookupName($table->getColumns()['tree_level']->getType()));

        static::assertArrayHasKey('bool', $table->getColumns());
        static::assertEquals(Types::BOOLEAN, Type::getTypeRegistry()->lookupName($table->getColumns()['bool']->getType()));

        static::assertArrayHasKey('locked', $table->getColumns());
        static::assertEquals(Types::BOOLEAN, Type::getTypeRegistry()->lookupName($table->getColumns()['locked']->getType()));

        static::assertArrayHasKey('password', $table->getColumns());
        static::assertEquals(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumns()['password']->getType()));

        static::assertArrayHasKey('string', $table->getColumns());
        static::assertEquals(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumns()['string']->getType()));

        static::assertArrayHasKey('timezone', $table->getColumns());
        static::assertEquals(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumns()['timezone']->getType()));

        static::assertArrayHasKey('cron_interval', $table->getColumns());
        static::assertEquals(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumns()['cron_interval']->getType()));

        static::assertArrayHasKey('date_interval', $table->getColumns());
        static::assertEquals(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumns()['date_interval']->getType()));

        static::assertArrayHasKey('email', $table->getColumns());
        static::assertEquals(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumns()['email']->getType()));

        static::assertArrayHasKey('remote_address', $table->getColumns());
        static::assertEquals(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumns()['remote_address']->getType()));

        static::assertArrayHasKey('number_range', $table->getColumns());
        static::assertEquals(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumns()['number_range']->getType()));

        static::assertArrayHasKey('blob', $table->getColumns());
        static::assertEquals(Types::BLOB, Type::getTypeRegistry()->lookupName($table->getColumns()['blob']->getType()));

        static::assertArrayHasKey('float', $table->getColumns());
        static::assertEquals(Types::DECIMAL, Type::getTypeRegistry()->lookupName($table->getColumns()['float']->getType()));

        static::assertArrayHasKey('tree_path', $table->getColumns());
        static::assertEquals(Types::TEXT, Type::getTypeRegistry()->lookupName($table->getColumns()['tree_path']->getType()));

        static::assertArrayHasKey('long_text', $table->getColumns());
        static::assertEquals(Types::TEXT, Type::getTypeRegistry()->lookupName($table->getColumns()['long_text']->getType()));
    }

    public function testForeignKeys(): void
    {
        $definition = $this->registry->get(TestEntityWithForeignKeysDefinition::class);

        $schemaBuilder = new SchemaBuilder();

        $table = $schemaBuilder->buildSchemaOfDefinition($definition);

        static::assertSame('id', $table->getPrimaryKey()?->getColumns()[0]);

        static::assertArrayHasKey('id', $table->getColumns());
        static::assertEquals(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumns()['id']->getType()));

        static::assertArrayHasKey('version_id', $table->getColumns());
        static::assertEquals(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumns()['version_id']->getType()));

        static::assertArrayHasKey('parent_id', $table->getColumns());
        static::assertEquals(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumns()['parent_id']->getType()));

        static::assertArrayHasKey('parent_version_id', $table->getColumns());
        static::assertEquals(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumns()['parent_version_id']->getType()));

        static::assertArrayHasKey('created_at', $table->getColumns());
        static::assertEquals(Types::DATETIME_MUTABLE, Type::getTypeRegistry()->lookupName($table->getColumns()['created_at']->getType()));

        static::assertArrayHasKey('updated_at', $table->getColumns());
        static::assertEquals(Types::DATETIME_MUTABLE, Type::getTypeRegistry()->lookupName($table->getColumns()['updated_at']->getType()));

        static::assertArrayHasKey('association_id', $table->getColumns());
        static::assertEquals(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumns()['association_id']->getType()));

        static::assertArrayHasKey('association_id2', $table->getColumns());
        static::assertEquals(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumns()['association_id2']->getType()));

        static::assertArrayHasKey('association_id3', $table->getColumns());
        static::assertEquals(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumns()['association_id3']->getType()));

        static::assertTrue($table->hasForeignKey('fk.test_entity_with_foreign_keys.association_id'));
        static::assertTrue($table->hasForeignKey('fk.test_entity_with_foreign_keys.association_id2'));
        static::assertTrue($table->hasForeignKey('fk.test_entity_with_foreign_keys.association_id3'));

        $associationFk = $table->getForeignKey('fk.test_entity_with_foreign_keys.association_id');

        static::assertSame('association_id', $associationFk->getLocalColumns()[0]);
        static::assertSame('test_association', $associationFk->getForeignTableName());
        static::assertSame('id', $associationFk->getForeignColumns()[0]);
        static::assertArrayHasKey('onUpdate', $associationFk->getOptions());
        static::assertEquals('CASCADE', $associationFk->getOptions()['onUpdate']);
        static::assertArrayHasKey('onDelete', $associationFk->getOptions());
        static::assertEquals('SET NULL', $associationFk->getOptions()['onDelete']);

        $associationFk2 = $table->getForeignKey('fk.test_entity_with_foreign_keys.association_id2');

        static::assertSame('association_id2', $associationFk2->getLocalColumns()[0]);
        static::assertSame('test_association', $associationFk2->getForeignTableName());
        static::assertSame('id', $associationFk2->getForeignColumns()[0]);
        static::assertArrayHasKey('onUpdate', $associationFk2->getOptions());
        static::assertEquals('CASCADE', $associationFk2->getOptions()['onUpdate']);
        static::assertArrayHasKey('onDelete', $associationFk2->getOptions());
        static::assertEquals('CASCADE', $associationFk2->getOptions()['onDelete']);

        $associationFk3 = $table->getForeignKey('fk.test_entity_with_foreign_keys.association_id3');

        static::assertSame('association_id3', $associationFk3->getLocalColumns()[0]);
        static::assertSame('test_association', $associationFk3->getForeignTableName());
        static::assertSame('id', $associationFk3->getForeignColumns()[0]);
        static::assertArrayHasKey('onUpdate', $associationFk3->getOptions());
        static::assertEquals('CASCADE', $associationFk3->getOptions()['onUpdate']);
        static::assertArrayHasKey('onDelete', $associationFk3->getOptions());
        static::assertEquals('RESTRICT', $associationFk3->getOptions()['onDelete']);
    }
}

/**
 * @internal
 */
class TestEntityWithSkippedFieldsDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_entity_with_skipped_fields';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('runtime', 'runtime'))->addFlags(new Runtime()),
            new FkField('relation_id', 'relationId', TestAssociationDefinition::class),
            new ManyToOneAssociationField('relation', 'relation_id', TestAssociationDefinition::class, 'id'),
            new TranslatedField('translated'),
        ]);
    }
}

/**
 * @internal
 */
class TestEntityWithAllPossibleFieldsDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_entity_with_all_possible_fields';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new CreatedByField(),
            new UpdatedByField(),
            new StateMachineStateField('state_id', 'stateId', OrderStates::STATE_MACHINE),
            new CreatedAtField(),
            new UpdatedAtField(),
            new DateTimeField('datetime', 'datetime'),
            new DateField('date', 'date'),
            new CartPriceField('cart_price', 'cartPrice'),
            new CalculatedPriceField('calculated_price', 'calculatedPrice'),
            new PriceField('price', 'price'),
            new PriceDefinitionField('price_definition', 'priceDefinition'),
            new JsonField('json', 'json'),
            new ListField('list', 'list'),
            new ConfigJsonField('config_json', 'configJson'),
            new CustomFields(),
            new BreadcrumbField(),
            new CashRoundingConfigField('cash_rounding_config', 'cashRoundingConfig'),
            new ObjectField('object', 'object'),
            new TaxFreeConfigField('tax_free_config', 'taxFreeConfig'),
            new TreeBreadcrumbField('tree_breadcrumb', 'treeBreadcrumb'),
            new VariantListingConfigField('variant_listing_config', 'variantListingConfig'),
            new VersionDataPayloadField('version_data_payload', 'versionDataPayload'),
            new ChildCountField(),
            new IntField('int', 'int'),
            new AutoIncrementField(),
            new TreeLevelField('tree_level', 'treeLevel'),
            new BoolField('bool', 'bool'),
            new LockedField(),
            new PasswordField('password', 'password'),
            new StringField('string', 'string'),
            new TimeZoneField('timezone', 'timezone'),
            new CronIntervalField('cron_interval', 'cronInterval'),
            new DateIntervalField('date_interval', 'dateInterval'),
            new EmailField('email', 'email'),
            new RemoteAddressField('remote_address', 'remoteAddress'),
            new NumberRangeField('number_range', 'numberRange'),
            new BlobField('blob', 'blob'),
            new FloatField('float', 'float'),
            new TreePathField('tree_path', 'treePath'),
            new LongTextField('long_text', 'longText'),
        ]);
    }
}

/**
 * @internal
 */
class TestEntityWithForeignKeysDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_entity_with_foreign_keys';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new ParentFkField(self::class),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new Required()),
            new FkField('association_id', 'associationId', TestAssociationDefinition::class),
            new ManyToOneAssociationField('association', 'association_id', TestAssociationDefinition::class, 'id'),
            new FkField('association_id2', 'associationId2', TestAssociationDefinition::class),
            (new ManyToOneAssociationField('association2', 'association_id2', TestAssociationDefinition::class, 'id'))->addFlags(new CascadeDelete()),
            new FkField('association_id3', 'associationId3', TestAssociationDefinition::class),
            (new ManyToOneAssociationField('association3', 'association_id3', TestAssociationDefinition::class, 'id'))->addFlags(new RestrictDelete()),
            new OneToManyAssociationField('children', self::class, 'parent_id'),
            new ManyToManyAssociationField('manyToMany', self::class, TestAssociationDefinition::class, 'test_entity_with_foreign_keys_id', 'test_association_id'),
            new OneToOneAssociationField('oneToOne', 'id', 'test_entity_with_foreign_keys_id', self::class, true),
        ]);
    }
}

/**
 * @internal
 */
class TestAssociationDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_association';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('name', 'name'),
        ]);
    }
}
