<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldPlainTestDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestTranslationDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SingleEntityDependencyTestDependencyDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SingleEntityDependencyTestDependencySubDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SingleEntityDependencyTestRootDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SingleEntityDependencyTestSubDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ToManyAssociationDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\TranslatableTestDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\TranslatableTestHydrator;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\TranslatableTestTranslationDefinition;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(EntityHydrator::class)]
class EntityHydratorTest extends TestCase
{
    private EntityHydrator $hydrator;

    private StaticDefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $container = new ContainerBuilder();
        $this->hydrator = new EntityHydrator($container);
        $container->set(EntityHydrator::class, $this->hydrator);

        $this->definitionInstanceRegistry = new StaticDefinitionInstanceRegistry(
            [
                FkExtensionFieldTest::class,
                CustomFieldPlainTestDefinition::class,
                CustomFieldTestDefinition::class,
                CustomFieldTestTranslationDefinition::class,
                TranslatableTestDefinition::class,
                TranslatableTestTranslationDefinition::class,
                SingleEntityDependencyTestRootDefinition::class,
                SingleEntityDependencyTestSubDefinition::class,
                SingleEntityDependencyTestDependencyDefinition::class,
                SingleEntityDependencyTestDependencySubDefinition::class,
                ToManyAssociationDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    public function testFkExtensionFieldHydration(): void
    {
        $definition = $this->definitionInstanceRegistry->get(FkExtensionFieldTest::class);

        $id = Uuid::randomBytes();
        $normal = Uuid::randomBytes();
        $extended = Uuid::randomBytes();

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'test',
                'test.normalFk' => $normal,
                'test.extendedFk' => $extended,
            ],
        ];

        $structs = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, $rows, 'test', Context::createDefaultContext());
        static::assertCount(1, $structs);

        /** @var ArrayEntity|null $first */
        $first = $structs->first();

        static::assertInstanceOf(ArrayEntity::class, $first);

        static::assertSame('test', $first->get('name'));

        static::assertSame(Uuid::fromBytesToHex($id), $first->get('id'));
        static::assertSame(Uuid::fromBytesToHex($normal), $first->get('normalFk'));

        static::assertTrue($first->hasExtension(EntityReader::FOREIGN_KEYS));
        /** @var ArrayStruct<string, mixed>|null $foreignKeys */
        $foreignKeys = $first->getExtension(EntityReader::FOREIGN_KEYS);

        static::assertInstanceOf(ArrayStruct::class, $foreignKeys);

        static::assertTrue($foreignKeys->has('extendedFk'));
        static::assertSame(Uuid::fromBytesToHex($extended), $foreignKeys->get('extendedFk'));
    }

    public function testTranslationWithZeroStringField(): void
    {
        $definition = $this->definitionInstanceRegistry->get(TranslatableTestDefinition::class);

        $id = Uuid::randomBytes();

        $rows = [
            [
                'test.id' => $id,
                'test.name' => '0',
                'test.translation.name' => '0',
            ],
        ];

        $container = new ContainerBuilder();
        $hydrator = new TranslatableTestHydrator($container);
        $container->set(TranslatableTestHydrator::class, $hydrator);

        $structs = $hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, $rows, 'test', Context::createDefaultContext());
        static::assertCount(1, $structs);

        static::assertEquals(1, $structs->count());

        $first = $structs->first();
        static::assertNotNull($first);
        static::assertSame('0', $first->get('name'));
        static::assertSame('0', $first->getTranslation('name'));
    }

    public function testCustomFieldHydrationWithoutTranslationWithoutInheritance(): void
    {
        $definition = $this->definitionInstanceRegistry->get(CustomFieldPlainTestDefinition::class);

        $id = Uuid::randomBytes();

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'example',
                'test.customFields' => '{"custom_test_text": "Example", "custom_test_check": null}',
            ],
        ];

        $structs = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, $rows, 'test', Context::createDefaultContext());
        static::assertCount(1, $structs);

        static::assertEquals(1, $structs->count());
        $first = $structs->first();
        static::assertNotNull($first);
        $customFields = $first->get('customFields');

        static::assertIsArray($customFields);
        static::assertCount(2, $customFields);
        static::assertSame('Example', $customFields['custom_test_text']);
        static::assertNull($customFields['custom_test_check']);
    }

    public function testCustomFieldHydrationWithTranslationWithInheritance(): void
    {
        $definition = $this->definitionInstanceRegistry->get(CustomFieldTestDefinition::class);

        $id = Uuid::randomBytes();
        $context = $this->createContext();

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'example',
                'test.customTranslated' => '{"custom_test_text": null, "custom_test_check": "0"}',
                'test.translation.customTranslated' => '{"custom_test_text": null, "custom_test_check": "0"}',
                'test.translation.fallback_1.customTranslated' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.parent.translation.customTranslated' => '{"custom_test_text": "PARENT DEUTSCH"}',
                'test.parent.translation.fallback_1.customTranslated' => '{"custom_test_text": "PARENT ENGLISH", "custom_test_check": "1"}',
            ],
        ];

        $structs = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, $rows, 'test', $context);
        static::assertCount(1, $structs);

        $first = $structs->first();
        static::assertNotNull($first);
        $customFields = $first->getTranslation('customTranslated');
        static::assertSame('PARENT ENGLISH', $customFields['custom_test_text']);
        static::assertSame('1', $customFields['custom_test_check']);

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'example',
                'test.customTranslated' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.translation.customTranslated' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.translation.fallback_1.customTranslated' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.parent.translation.customTranslated' => '{"custom_test_text": "PARENT DEUTSCH"}',
                'test.parent.translation.fallback_1.customTranslated' => '{"custom_test_text": "PARENT ENGLISH", "custom_test_check": "1"}',
            ],
        ];

        $structs = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, $rows, 'test', $context);
        $first = $structs->first();
        static::assertNotNull($first);

        $customFields = $first->getTranslation('customTranslated');
        static::assertSame('PARENT ENGLISH', $customFields['custom_test_text']);
        static::assertSame('1', $customFields['custom_test_check']);

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'example',
                'test.customTranslated' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.translation.customTranslated' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.translation.fallback_1.customTranslated' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.parent.translation.customTranslated' => '{"custom_test_text": null}',
                'test.parent.translation.fallback_1.customTranslated' => '{"custom_test_text": "PARENT ENGLISH", "custom_test_check": "0"}',
            ],
        ];

        $structs = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, $rows, 'test', $context);
        $first = $structs->first();
        static::assertNotNull($first);

        $customFields = $first->getTranslation('customTranslated');
        static::assertSame('PARENT ENGLISH', $customFields['custom_test_text']);
        static::assertSame('0', $customFields['custom_test_check']);

        $context = $this->createContext(true, [Uuid::randomHex()]);
        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'example',
                'test.customTranslated' => '{}',
                'test.translation.customTranslated' => '{"custom_test_inheritance": "CHILD ENGLISH"}',
                'test.translation.fallback_1.customTranslated' => '{"custom_test_inheritance": "CHILD GERMAN"}',
                'test.translation.fallback_2.customTranslated' => '{"custom_test_inheritance": "CHILD SWISS"}',
                'test.parent.translation.customTranslated' => '{"custom_test_text": "PARENT ENGLISH", "custom_test_inheritance": "PARENT ENGLISH"}',
                'test.parent.translation.fallback_1.customTranslated' => '{"custom_test_check": "0", "custom_test_inheritance": "PARENT GERMAN"}',
                'test.parent.translation.fallback_2.customTranslated' => '{"custom_test_inheritance": "PARENT SWISS"}',
            ],
        ];

        $structs = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, $rows, 'test', $context);
        $first = $structs->first();
        static::assertNotNull($first);

        $customFields = $first->get('customTranslated');
        $translated = $first->getTranslation('customTranslated');
        static::assertArrayNotHasKey('custom_test_text', $customFields);
        static::assertSame('PARENT ENGLISH', $translated['custom_test_text']);
        static::assertSame('CHILD SWISS', $customFields['custom_test_inheritance']);
        static::assertSame('CHILD SWISS', $translated['custom_test_inheritance']);
        static::assertArrayNotHasKey('custom_test_check', $customFields);
        static::assertSame('0', $translated['custom_test_check']);
    }

    public function testCustomFieldHydrationWithTranslationWithoutInheritance(): void
    {
        $definition = $this->definitionInstanceRegistry->get(CustomFieldTestDefinition::class);

        $id = Uuid::randomBytes();
        $context = $this->createContext(false);

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'example',
                'test.customTranslated' => '{"custom_test_text": null, "custom_test_check": "1"}',
                'test.translation.customTranslated' => '{"custom_test_text": null, "custom_test_check": "1"}',
                'test.translation.fallback_1.customTranslated' => '{"custom_test_text": "Example", "custom_test_check": null}',
            ],
        ];

        $structs = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, $rows, 'test', $context);
        static::assertCount(1, $structs);

        $first = $structs->first();
        static::assertNotNull($first);
        $customFields = $first->get('customTranslated');
        static::assertSame('Example', $customFields['custom_test_text']);
        static::assertNull($customFields['custom_test_check']);
    }

    public function testCustomFieldHydrationWithoutTranslationWithInheritance(): void
    {
        $definition = $this->definitionInstanceRegistry->get(CustomFieldTestDefinition::class);

        $id = Uuid::randomBytes();
        $context = $this->createContext();

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'example',
                'test.custom' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.custom.inherited' => '{"custom_test_text": "PARENT", "custom_test_check": "0"}',
            ],
        ];

        $structs = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, $rows, 'test', $context);
        static::assertCount(1, $structs);

        $first = $structs->first();
        static::assertNotNull($first);
        $customFields = $first->get('custom');

        static::assertSame('PARENT', $customFields['custom_test_text']);
        static::assertSame('0', $customFields['custom_test_check']);

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'example',
                'test.custom' => '{"custom_test_text": null, "custom_test_check": "1"}',
            ],
        ];

        $structs = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, $rows, 'test', $context);
        static::assertCount(1, $structs);

        $first = $structs->first();
        static::assertNotNull($first);
        $customFields = $first->get('custom');

        static::assertNull($customFields['custom_test_text']);
        static::assertSame('1', $customFields['custom_test_check']);
    }

    public function testSingleEntityDependencyWithDifferentlyLoadedAssociations(): void
    {
        $definition = $this->definitionInstanceRegistry->get(SingleEntityDependencyTestRootDefinition::class);

        $pickupPointId = Uuid::randomBytes();
        $warehouseId = Uuid::randomBytes();
        $zipcodeId = Uuid::randomBytes();
        $countryId = Uuid::randomBytes();

        $context = $this->createContext();

        $rowWithoutWarehouseZipcodeHydration = [
            'test.id' => $pickupPointId,
            'test.name' => 'PickupPoint',
            'test.warehouseId' => $warehouseId,
            'test.warehouse.id' => $warehouseId,
            'test.warehouse.name' => 'Warehouse',
            'test.warehouse.zipcodeId' => $zipcodeId,
            'test.zipcodeId' => $zipcodeId,
            'test.zipcode.id' => $zipcodeId,
            'test.zipcode.zipcode' => '00000',
            'test.zipcode.countryId' => $countryId,
            'test.zipcode.country.id' => $countryId,
            'test.zipcode.country.iso' => 'DE',
        ];

        $rowWithWarehouseZipcodeHydration = [
            'test.id' => $pickupPointId,
            'test.name' => 'PickupPoint',
            'test.warehouseId' => $warehouseId,
            'test.warehouse.id' => $warehouseId,
            'test.warehouse.name' => 'Warehouse',
            'test.warehouse.zipcodeId' => $zipcodeId,
            'test.warehouse.zipcode.id' => $zipcodeId,
            'test.warehouse.zipcode.zipcode' => '00000',
            'test.warehouse.zipcode.countryId' => $countryId,
            'test.zipcodeId' => $zipcodeId,
            'test.zipcode.id' => $zipcodeId,
            'test.zipcode.zipcode' => '00000',
            'test.zipcode.countryId' => $countryId,
            'test.zipcode.country.id' => $countryId,
            'test.zipcode.country.iso' => 'DE',
        ];

        $structsWithoutWarehouseZipcodeHydration = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, [$rowWithoutWarehouseZipcodeHydration], 'test', $context);
        $first = $structsWithoutWarehouseZipcodeHydration->first();
        static::assertNotNull($first);
        $country = $first->get('zipcode')->get('country');
        static::assertInstanceOf(ArrayEntity::class, $country);
        static::assertEquals(Uuid::fromBytesToHex($countryId), $country->get('id'));
        static::assertArrayHasKey('zipcode', $first->get('warehouse')->all());
        static::assertNull($first->get('warehouse')->all()['zipcode']);

        $structsWithWarehouseZipcodeHydration = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, [$rowWithWarehouseZipcodeHydration], 'test', $context);
        $first = $structsWithWarehouseZipcodeHydration->first();
        static::assertNotNull($first);
        static::assertNotNull($first->get('zipcode')->get('country'));
        static::assertArrayHasKey('zipcode', $first->get('warehouse')->all());
        static::assertNotNull($first->get('warehouse')->all()['zipcode']);
    }

    public function testNotLoadedManyToManyAssociationsAreInitializedWithNullForArrayEntities(): void
    {
        $definition = $this->definitionInstanceRegistry->get(ToManyAssociationDefinition::class);

        $id = Uuid::randomBytes();

        $context = $this->createContext();

        $rowWithoutToManyHydration = [
            'test.id' => $id,
        ];

        $structsWithoutToManyHydration = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, [$rowWithoutToManyHydration], 'test', $context);
        $first = $structsWithoutToManyHydration->first();
        static::assertNotNull($first);
        static::assertEquals(Uuid::fromBytesToHex($id), $first->getId());
        static::assertArrayHasKey('toMany', $first->all());
        static::assertNull($first->all()['toMany']);
    }

    /**
     * @param string[] $additionalLanguages
     */
    private function createContext(bool $inheritance = true, array $additionalLanguages = []): Context
    {
        return new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Uuid::randomHex(), ...$additionalLanguages, Defaults::LANGUAGE_SYSTEM],
            Defaults::LIVE_VERSION,
            1.0,
            $inheritance
        );
    }
}

/**
 * @internal
 */
class FkExtensionFieldTest extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'fk_extension_test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey()),
            (new StringField('name', 'name'))->addFlags(new ApiAware()),
            (new FkField('normal_fk', 'normalFk', ProductDefinition::class))->addFlags(new ApiAware()),

            (new FkField('extended_fk', 'extendedFk', ProductDefinition::class))->addFlags(new ApiAware(), new Extension()),
        ]);
    }
}
