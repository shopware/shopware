<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderStates;
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
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TaxFreeConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeBreadcrumbField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VariantListingConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionDataPayloadField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\SchemaGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.7.0 - Will be removed with \Shopware\Core\Framework\DataAbstractionLayer\SchemaGenerator
 */
#[Package('core')]
#[CoversClass(SchemaGenerator::class)]
class SchemaGeneratorTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $this->registry = new StaticDefinitionInstanceRegistry(
            [
                TestEntityWithAllPossibleFieldsDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    public function testDifferentFieldTypes(): void
    {
        $definition = $this->registry->get(TestEntityWithAllPossibleFieldsDefinition::class);

        $schemaBuilder = new SchemaGenerator();

        $table = $schemaBuilder->generate($definition);

        static::assertNotEmpty($table);
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
