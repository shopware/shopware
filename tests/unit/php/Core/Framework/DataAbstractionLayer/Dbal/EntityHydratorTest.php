<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
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
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator
 */
class EntityHydratorTest extends TestCase
{
    private EntityHydrator $hydrator;

    private StaticDefinitionInstanceRegistry $definitionInstanceRegistry;

    public function setUp(): void
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

        /** @var EntityCollection<Entity> $structs */
        $structs = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, $rows, 'test', Context::createDefaultContext());
        static::assertCount(1, $structs);

        static::assertEquals(1, $structs->count());
        $first = $structs->first();
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
        $customFields = $first->get('customTranslated');
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

        $customFields = $first->get('customTranslated');
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

        $customFields = $first->get('customTranslated');
        static::assertSame('PARENT ENGLISH', $customFields['custom_test_text']);
        static::assertSame('0', $customFields['custom_test_check']);
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
        static::assertNotNull($structsWithoutWarehouseZipcodeHydration->first()->get('zipcode')->get('country'));
        static::assertEquals(Uuid::fromBytesToHex($countryId), $structsWithoutWarehouseZipcodeHydration->first()->get('zipcode')->get('country')->get('id'));
        static::assertArrayHasKey('zipcode', $structsWithoutWarehouseZipcodeHydration->first()->get('warehouse')->all());
        static::assertNull($structsWithoutWarehouseZipcodeHydration->first()->get('warehouse')->all()['zipcode']);

        $structsWithWarehouseZipcodeHydration = $this->hydrator->hydrate(new EntityCollection(), $definition->getEntityClass(), $definition, [$rowWithWarehouseZipcodeHydration], 'test', $context);
        static::assertNotNull($structsWithWarehouseZipcodeHydration->first()->get('zipcode')->get('country'));
        static::assertArrayHasKey('zipcode', $structsWithWarehouseZipcodeHydration->first()->get('warehouse')->all());
        static::assertNotNull($structsWithWarehouseZipcodeHydration->first()->get('warehouse')->all()['zipcode']);
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
        static::assertEquals(Uuid::fromBytesToHex($id), $structsWithoutToManyHydration->first()->getId());
        static::assertArrayHasKey('toMany', $structsWithoutToManyHydration->first()->all());
        static::assertNull($structsWithoutToManyHydration->first()->all()['toMany']);
    }

    private function createContext(bool $inheritance = true): Context
    {
        return new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Uuid::randomHex(), Defaults::LANGUAGE_SYSTEM],
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
