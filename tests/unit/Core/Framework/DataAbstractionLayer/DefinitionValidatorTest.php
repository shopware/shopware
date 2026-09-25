<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity as EntityAttribute;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\OneToOne;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ReferenceVersion;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Version;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeMappingDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityAgg;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\_fixtures\CascadingManyToOneEntity;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Validation\Fixtures\DefinitionStub;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Validation\Fixtures\DefinitionWithNonStorageAwarePrimaryKeyStub;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DefinitionValidator::class)]
class DefinitionValidatorTest extends TestCase
{
    /**
     * @param list<string> $expectedMessages
     * @param list<string> $dbPrimaryKeys
     */
    #[DataProvider('primaryKeyMismatchProvider')]
    public function testPrimaryKeyMismatchReportsViolation(array $dbPrimaryKeys, array $expectedMessages): void
    {
        $definition = new DefinitionStub();
        $validator = $this->createValidatorWithTable($definition, $dbPrimaryKeys);

        $violations = $validator->validate();
        $definitionViolations = $violations[$definition::class] ?? [];

        // Filter to only primary key violations
        $primaryKeyViolations = array_filter(
            $definitionViolations,
            static fn (string $violation): bool => str_contains($violation, 'Primary key mismatch')
        );

        static::assertCount(1, $primaryKeyViolations, 'Expected 1 primary key violation, but got: ' . implode(', ', $primaryKeyViolations));
        $violation = reset($primaryKeyViolations);

        foreach ($expectedMessages as $expectedMessage) {
            static::assertStringContainsString($expectedMessage, $violation);
        }
    }

    /**
     * @return \Generator<string, array{list<string>, list<string>}>
     */
    public static function primaryKeyMismatchProvider(): \Generator
    {
        yield 'mismatched primary key' => [
            ['foo'],
            [
                'Primary key mismatch in entity "definition_validator_test"',
                'Table has PRIMARY KEY (foo)',
                'entity definition has PrimaryKey flags on (id)',
            ],
        ];

        yield 'no primary key' => [
            [],
            [
                'Primary key mismatch in entity "definition_validator_test"',
                'Table has PRIMARY KEY ()',
                'entity definition has PrimaryKey flags on (id)',
            ],
        ];
    }

    public function testPrimaryKeyMatchReportsNoViolation(): void
    {
        $definition = new DefinitionStub();
        $validator = $this->createValidatorWithTable($definition, ['id']);

        $violations = $validator->validate();
        $definitionViolations = $violations[$definition::class] ?? [];

        // Filter to only primary key violations
        $primaryKeyViolations = array_filter(
            $definitionViolations,
            static fn (string $violation): bool => str_contains($violation, 'Primary key mismatch')
        );

        static::assertEmpty($primaryKeyViolations, 'Expected no primary key violations, but got: ' . implode(', ', $primaryKeyViolations));
    }

    public function testPrimaryKeyValidationSkipsNonExistentTable(): void
    {
        $definition = new DefinitionStub();
        $validator = $this->createValidatorWithNonExistentTable($definition);

        $violations = $validator->validate();
        $definitionViolations = $violations[$definition::class] ?? [];

        // Filter to only primary key violations
        $primaryKeyViolations = array_filter(
            $definitionViolations,
            static fn (string $violation): bool => str_contains($violation, 'Primary key mismatch')
        );

        // When table doesn't exist in the schema, validatePrimaryKeyConsistency skips validation
        static::assertEmpty($primaryKeyViolations, 'Expected no primary key violations when table does not exist, but got: ' . implode(', ', $primaryKeyViolations));

        static::assertContains(
            'Table "definition_validator_test" referenced by definition but not found in schema',
            $definitionViolations
        );
    }

    public function testMissingEntityNameConstantIsReportedWithoutFatalError(): void
    {
        $definition = new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'definition_validator_test';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([]);
            }
        };

        $validator = $this->createValidatorWithTable($definition, ['id']);

        $violations = $validator->validate();

        static::assertContains(
            \sprintf('ENTITY_NAME constant Missing in %s', $definition->getClass()),
            $violations[$definition->getClass()] ?? []
        );
    }

    public function testPrimaryKeyValidationSkipsNonStorageAwareFields(): void
    {
        // Use a definition with a non-StorageAware field marked as PrimaryKey
        $definition = new DefinitionWithNonStorageAwarePrimaryKeyStub();
        $validator = $this->createValidatorWithTable($definition, ['id']);

        $violations = $validator->validate();
        $definitionViolations = $violations[$definition::class] ?? [];

        // Filter to only primary key violations
        $primaryKeyViolations = array_filter(
            $definitionViolations,
            static fn (string $violation): bool => str_contains($violation, 'Primary key mismatch')
        );

        // The non-StorageAware field should be skipped (line 990 coverage)
        // So only 'id' should be considered, which matches the database
        static::assertEmpty($primaryKeyViolations, 'Non-StorageAware primary key fields should be ignored');
    }

    public function testForeignKeyReferencingFullCompositePrimaryKeyReportsNoViolation(): void
    {
        $parent = $this->createTable('parent', ['id', 'version_id', 'code', 'name'], ['id', 'version_id']);
        $child = $this->createTable('child', ['id', 'parent_id', 'parent_version_id']);
        $child->addForeignKeyConstraint('parent', ['parent_id', 'parent_version_id'], ['id', 'version_id'], [], 'fk_child_parent_id');

        static::assertSame([], $this->getForeignKeyViolations(new Schema([$parent, $child])));
    }

    public function testForeignKeyReferencingCompositePrimaryKeyInWrongOrderReportsViolation(): void
    {
        $parent = $this->createTable('parent', ['id', 'version_id', 'code', 'name'], ['id', 'version_id']);
        $child = $this->createTable('child', ['id', 'parent_version_id', 'parent_id']);
        // FK references (version_id, id) but PK is (id, version_id) — column order mismatch
        $child->addForeignKeyConstraint('parent', ['parent_version_id', 'parent_id'], ['version_id', 'id'], [], 'fk_child_parent_wrong_order');

        $violations = $this->getForeignKeyViolations(new Schema([$parent, $child]));

        static::assertCount(1, $violations);
        static::assertStringContainsString('fk_child_parent_wrong_order', $violations[0]);
    }

    public function testForeignKeyReferencingPrefixOfCompositePrimaryKeyReportsViolation(): void
    {
        $parent = $this->createTable('parent', ['id', 'version_id', 'code', 'name'], ['id', 'version_id']);
        $child = $this->createTable('child', ['id', 'parent_id']);
        $child->addForeignKeyConstraint('parent', ['parent_id'], ['id'], [], 'fk_child_parent_id');

        $violations = $this->getForeignKeyViolations(new Schema([$parent, $child]));

        static::assertCount(1, $violations);
        static::assertStringContainsString('Foreign key "fk_child_parent_id" on table "child" references parent(id)', $violations[0]);
        static::assertStringContainsString('restrict_fk_on_non_standard_key', $violations[0]);
    }

    public function testForeignKeyReferencingSingleColumnPrimaryKeyReportsNoViolation(): void
    {
        $parent = $this->createTable('parent', ['id', 'name'], ['id']);
        $child = $this->createTable('child', ['id', 'parent_id']);
        $child->addForeignKeyConstraint('parent', ['parent_id'], ['id'], [], 'fk_child_parent_id');

        static::assertSame([], $this->getForeignKeyViolations(new Schema([$parent, $child])));
    }

    public function testForeignKeyReferencingUniqueIndexReportsNoViolation(): void
    {
        $parent = $this->createTable('parent', ['id', 'code'], ['id']);
        $parent->addUniqueIndex(['code'], 'uniq_parent_code');
        $child = $this->createTable('child', ['id', 'parent_code']);
        $child->addForeignKeyConstraint('parent', ['parent_code'], ['code'], [], 'fk_child_parent_code');

        static::assertSame([], $this->getForeignKeyViolations(new Schema([$parent, $child])));
    }

    public function testForeignKeyReferencingNonKeyColumnReportsViolation(): void
    {
        $parent = $this->createTable('parent', ['id', 'name'], ['id']);
        $child = $this->createTable('child', ['id', 'parent_name']);
        $child->addForeignKeyConstraint('parent', ['parent_name'], ['name'], [], 'fk_child_parent_name');

        $violations = $this->getForeignKeyViolations(new Schema([$parent, $child]));

        static::assertCount(1, $violations);
        static::assertStringContainsString('references parent(name)', $violations[0]);
    }

    public function testSelfReferencingForeignKeyToOwnPrimaryKeyReportsNoViolation(): void
    {
        $table = $this->createTable('tree', ['id', 'parent_id'], ['id']);
        $table->addForeignKeyConstraint('tree', ['parent_id'], ['id'], [], 'fk_tree_parent_id');

        static::assertSame([], $this->getForeignKeyViolations(new Schema([$table])));
    }

    public function testForeignKeyReferencingPrefixUniqueIndexReportsViolation(): void
    {
        $parent = $this->createTable('parent', ['id', 'code'], ['id']);
        // A unique index on a column prefix cannot back a foreign key.
        $parent->addUniqueIndex(['code'], 'uniq_parent_code', ['lengths' => [191]]);
        $child = $this->createTable('child', ['id', 'parent_code']);
        $child->addForeignKeyConstraint('parent', ['parent_code'], ['code'], [], 'fk_child_parent_code');

        $violations = $this->getForeignKeyViolations(new Schema([$parent, $child]));

        static::assertCount(1, $violations);
        static::assertStringContainsString('references parent(code)', $violations[0]);
    }

    public function testForeignKeyReferencingPartialUniqueIndexReportsViolation(): void
    {
        $parent = $this->createTable('parent', ['id', 'code'], ['id']);
        // A partial (predicate) unique index cannot back a foreign key.
        $parent->addUniqueIndex(['code'], 'uniq_parent_code', ['where' => 'code IS NOT NULL']);
        $child = $this->createTable('child', ['id', 'parent_code']);
        $child->addForeignKeyConstraint('parent', ['parent_code'], ['code'], [], 'fk_child_parent_code');

        $violations = $this->getForeignKeyViolations(new Schema([$parent, $child]));

        static::assertCount(1, $violations);
        static::assertStringContainsString('references parent(code)', $violations[0]);
    }

    public function testForeignKeyReferencingTableOutsideSchemaReportsNoViolation(): void
    {
        $child = $this->createTable('child', ['id', 'parent_id'], ['id']);
        $child->addForeignKeyConstraint('parent', ['parent_id'], ['id'], [], 'fk_child_parent_id');

        static::assertSame([], $this->getForeignKeyViolations(new Schema([$child])));
    }

    public function testToleratedForeignKeyReportsNoViolation(): void
    {
        $parent = $this->createTable('parent', ['id', 'version_id', 'name'], ['id', 'version_id']);
        $child = $this->createTable('child', ['id', 'parent_id']);
        $child->addForeignKeyConstraint('parent', ['parent_id'], ['id'], [], 'fk_child_parent_id');

        static::assertSame(
            [],
            $this->getForeignKeyViolations(new Schema([$parent, $child]), ['fk_child_parent_id'])
        );
    }

    /**
     * @deprecated tag:v6.8.0 - should be removed when FEATURE_GATED_IGNORE_FIELDS is cleared
     */
    #[DataProvider('featureGatedIgnoreFieldProvider')]
    public function testFeatureGatedIgnoreFieldsAreValidatedWithFeatureActive(string $key): void
    {
        [$entityName, $fieldName] = explode('.', $key);
        static::assertNotEmpty($entityName);

        Feature::fake([], function () use ($entityName, $fieldName): void {
            static::assertSame([], $this->getUnmappedColumnViolations($entityName, $fieldName));
        });

        Feature::fake(['v6.8.0.0'], function () use ($entityName, $fieldName): void {
            static::assertSame(
                [\sprintf('Column %s has no configured field', $fieldName)],
                $this->getUnmappedColumnViolations($entityName, $fieldName)
            );
        });
    }

    /**
     * @deprecated tag:v6.8.0 - should be removed when FEATURE_GATED_IGNORE_FIELDS is cleared
     */
    public function testIgnoreFieldsAreStillIgnoredWithFeatureActive(): void
    {
        Feature::fake(['v6.8.0.0'], function (): void {
            static::assertSame([], $this->getUnmappedColumnViolations('product', 'cover'));
        });
    }

    /**
     * @deprecated tag:v6.8.0 - should be removed when FEATURE_GATED_IGNORE_FIELDS is cleared
     *
     * @return \Generator<string, array{string}>
     */
    public static function featureGatedIgnoreFieldProvider(): \Generator
    {
        yield 'customer billing address' => ['customer.defaultBillingAddress'];
        yield 'customer shipping address' => ['customer.defaultShippingAddress'];
        yield 'customer address billing customer' => ['customer_address.defaultBillingAddressCustomer'];
        yield 'customer address shipping customer' => ['customer_address.defaultShippingAddressCustomer'];
        yield 'order billing address' => ['order.billingAddress'];
        yield 'order address billing order' => ['order_address.billingAddressOrder'];
    }

    public function testForeignKeyViolationIsAttributedToOwningDefinition(): void
    {
        $parent = $this->createTable('parent', ['id', 'version_id'], ['id', 'version_id']);
        $child = $this->createTable('child', ['id', 'parent_id']);
        $child->addForeignKeyConstraint('parent', ['parent_id'], ['id'], [], 'fk_child_parent_id');

        $schemaManager = static::createStub(AbstractSchemaManager::class);
        $schemaManager->method('introspectSchema')->willReturn(new Schema([$parent, $child]));

        $connection = static::createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $owningDefinition = new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'child';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([]);
            }
        };
        $owningClass = $owningDefinition->getClass();

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([]);
        $registry->method('getByEntityName')->willReturnCallback(
            fn (string $name) => match ($name) {
                'child' => $owningDefinition,
                default => throw new DefinitionNotFoundException($name),
            }
        );

        $violations = (new DefinitionValidator($registry, $connection))->validate();

        $registryFkViolations = array_filter(
            $violations[DefinitionInstanceRegistry::class] ?? [],
            static fn (string $v): bool => str_contains($v, 'not a complete PRIMARY or UNIQUE key')
        );
        static::assertEmpty($registryFkViolations, 'FK violation should not fall back to the registry key when a definition owns the table');

        static::assertArrayHasKey($owningClass, $violations);
        $ownerFkViolations = array_filter(
            $violations[$owningClass],
            static fn (string $v): bool => str_contains($v, 'not a complete PRIMARY or UNIQUE key')
        );
        static::assertCount(1, $ownerFkViolations);
        static::assertStringContainsString('fk_child_parent_id', reset($ownerFkViolations));
    }

    public function testCascadeDeleteOnAnAttributeEntityManyToOneIsReportedUnderTheEntity(): void
    {
        $violations = $this->createValidatorWithTable($this->attributeEntityDefinition(CascadingManyToOneEntity::class), ['id'])->validate();

        static::assertArrayNotHasKey(AttributeEntityDefinition::class, $violations);
        static::assertArrayHasKey(CascadingManyToOneEntity::class, $violations);
        static::assertContains(
            'Remove cascade delete in definition ' . CascadingManyToOneEntity::class . ' association: currency. Many to one association should not have a cascade delete',
            $violations[CascadingManyToOneEntity::class]
        );
    }

    public function testMessagesNameAttributeEntitiesByTheirEntityClass(): void
    {
        $violations = $this->createValidatorWithTable(
            $this->attributeEntityDefinition(CascadingManyToOneEntity::class),
            ['id'],
            new CurrencyDefinition(),
        )->validate();

        static::assertContains(
            'Missing reverse one-to-many association for ' . CascadingManyToOneEntity::class . ' <-> ' . CurrencyDefinition::class . ' (currency)',
            $violations[CascadingManyToOneEntity::class]
        );
        static::assertStringNotContainsString(AttributeEntityDefinition::class, implode("\n", array_merge(...array_values($violations))));
    }

    public function testMappingViolationsNameTheMappingByItsEntityName(): void
    {
        $metas = [];
        foreach ((new AttributeEntityCompiler())->compile(ValidatedManyToManyEntity::class) as $meta) {
            $metas[$meta['type']] = $meta;
        }
        static::assertArrayHasKey('entity', $metas);
        static::assertArrayHasKey('mapping', $metas);

        $violations = $this->createValidatorWithTable(
            new AttributeEntityDefinition($metas['entity']),
            ['id'],
            new CurrencyDefinition(),
            new AttributeMappingDefinition(['fields' => []] + $metas['mapping']),
        )->validate();

        static::assertContains(
            'Missing field currency_id in definition currency_validated_many_to_many',
            $violations['currency_validated_many_to_many'] ?? []
        );
        static::assertStringNotContainsString(AttributeMappingDefinition::class, implode("\n", array_merge(...array_values($violations))));
    }

    public function testChecksAcrossTheValidatorNameTheAttributeEntity(): void
    {
        $metas = [];
        foreach ((new AttributeEntityCompiler())->compile(FlawedAttributeEntity::class) as $meta) {
            $metas[$meta['type']] = $meta;
        }
        static::assertArrayHasKey('entity', $metas);
        static::assertArrayHasKey('mapping', $metas);
        $untranslated = array_map(static fn (array $field): array => ['translated' => false] + $field, $metas['entity']['fields']);

        $violations = $this->createValidatorWithTable(
            new AttributeEntityDefinition($metas['entity']),
            ['id'],
            new CurrencyDefinition(),
            new AttributeMappingDefinition($metas['mapping']),
            new AttributeTranslationDefinition(['fields' => $untranslated] + $metas['entity']),
        )->validate();

        $entity = FlawedAttributeEntity::class;
        static::assertArrayHasKey($entity, $violations);
        static::assertContains('Missing reverse one-to-one association for ' . $entity . ' <-> ' . CurrencyDefinition::class . ' (currency)', $violations[$entity]);
        static::assertContains('Setter "setCurrencyList" of Entity "' . $entity . '" is nullable, but shouldn\'t allow null as it is a toMany association.', $violations[$entity]);
        static::assertContains('Association flawed_attribute_entity.currencyList does not end with a \'s\'.', $violations[$entity]);
        static::assertContains('Field readonlyProperty in entity struct should not be readonly in ' . $entity . ', as it needs to be writable by the DAL, see https://developer.shopware.com/docs/guides/plugins/plugins/framework/data-handling/add-custom-complex-data.html#entity-class', $violations[$entity]);
        static::assertContains('Field readonlyProperty in entity struct is missing in ' . $entity, $violations[$entity]);
        static::assertContains(
            'Field `name` defined in `' . $entity . '`, but missing in `flawed_attribute_entity_translation`',
            (array) ($violations['flawed_attribute_entity_translation'] ?? [])
        );
    }

    /**
     * @param class-string<Entity> $entityClass
     * @param list<string> $expectedViolations
     */
    #[DataProvider('foreignKeyNextToManyToManyProvider')]
    public function testManyToManyDoesNotRequireAVersionReferenceOnTheDefinition(string $entityClass, array $expectedViolations): void
    {
        $metas = [];
        foreach ((new AttributeEntityCompiler())->compile($entityClass) as $meta) {
            $metas[$meta['type']] = $meta;
        }
        static::assertArrayHasKey('entity', $metas);
        static::assertArrayHasKey('mapping', $metas);

        $violations = $this->createValidatorWithTable(
            new AttributeEntityDefinition($metas['entity']),
            ['id'],
            $this->attributeEntityDefinition(TrackEntity::class),
            new AttributeMappingDefinition($metas['mapping']),
        )->validate();

        static::assertSame(
            $expectedViolations,
            array_values(array_filter(
                $violations[$entityClass] ?? [],
                static fn (string $violation): bool => str_contains($violation, 'Missing version reference')
            ))
        );
    }

    /**
     * @return \Generator<string, array{class-string<Entity>, list<string>}>
     */
    public static function foreignKeyNextToManyToManyProvider(): \Generator
    {
        yield 'version reference present, nothing to report' => [PlaylistEntity::class, []];

        yield 'version reference missing, reported once for the many-to-one' => [
            AlbumEntity::class,
            ['Missing version reference for foreign key column track.id for definition association album.leadSingle'],
        ];
    }

    public function testAttributeEntitiesKeepTheirOwnViolations(): void
    {
        $violations = $this->createAttributeEntityValidator(
            false,
            $this->attributeEntityDefinition(CascadingManyToOneEntity::class),
            $this->attributeEntityDefinition(AttributeEntityAgg::class),
        )->validate();

        static::assertSame(
            ['Table "cascading_many_to_one" referenced by definition but not found in schema'],
            $violations[CascadingManyToOneEntity::class]
        );
        static::assertSame(
            ['Table "attribute_entity_agg" referenced by definition but not found in schema'],
            $violations[AttributeEntityAgg::class]
        );
    }

    public function testAttributeEntityNeedsNoAccessorsOrEntityNameConstant(): void
    {
        $violations = $this->createValidatorWithTable($this->attributeEntityDefinition(CascadingManyToOneEntity::class), ['id'])->validate();

        static::assertArrayHasKey(CascadingManyToOneEntity::class, $violations);
        static::assertSame([], array_values(array_filter(
            $violations[CascadingManyToOneEntity::class],
            static fn (string $violation): bool => str_contains($violation, 'getter') || str_contains($violation, 'setter') || str_contains($violation, 'ENTITY_NAME')
        )));
    }

    public function testAttributeEntitiesInTestNamespacesAreSkipped(): void
    {
        $violations = $this->createAttributeEntityValidator(true, $this->attributeEntityDefinition(CascadingManyToOneEntity::class))->validate();

        static::assertArrayNotHasKey(CascadingManyToOneEntity::class, $violations);
        static::assertArrayNotHasKey(AttributeEntityDefinition::class, $violations);
    }

    public function testToManyAssociationNamesAreCheckedAgainstThePluralOfTheAttributeEntity(): void
    {
        $violations = $this->createValidatorWithRegisteredAttributeEntities(ScorecardEntity::class, CriterionEntity::class)->validate();

        static::assertSame(
            [
                'Association scorecard.criterion does not end with a \'s\'.',
                'Association scorecard.criterionPool does not end with a \'s\'.',
            ],
            array_values(array_filter(
                $violations[ScorecardEntity::class],
                static fn (string $violation): bool => str_contains($violation, 'does not end with')
            ))
        );
    }

    public function testVersionReferenceToAnotherAttributeEntityDoesNotHideAMissingOne(): void
    {
        $violations = $this->createValidatorWithRegisteredAttributeEntities(ScorecardEntity::class, CriterionEntity::class)->validate();

        static::assertSame(
            ['Missing version reference for foreign key column scorecard.id for definition association criterion.scorecard'],
            array_values(array_filter(
                $violations[CriterionEntity::class],
                static fn (string $violation): bool => str_contains($violation, 'Missing version reference')
            ))
        );
    }

    /**
     * A column that no field maps to is reported as a violation, unless the `<entity>.<column>` key is
     * ignored. The reported violations are therefore the observable behaviour of the ignore lists.
     *
     * @param non-empty-string $entityName
     *
     * @return list<string>
     */
    private function getUnmappedColumnViolations(string $entityName, string $columnName): array
    {
        $definition = new class extends EntityDefinition {
            // the entity name is provided per scenario, so the constant cannot carry it
            public const ENTITY_NAME = '';

            /**
             * @var non-empty-string
             */
            public string $name = 'not_set';

            public function getEntityName(): string
            {
                return $this->name;
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([]);
            }
        };
        $definition->name = $entityName;

        $table = static::createStub(Table::class);
        $table->method('getName')->willReturn($entityName);
        $table->method('getColumns')->willReturn([new Column($columnName, Type::getType(Types::BINARY))]);

        $schema = static::createStub(Schema::class);
        $schema->method('hasTable')->willReturn(true);
        $schema->method('getTable')->willReturn($table);
        $schema->method('getTables')->willReturn([$table]);

        $schemaManager = static::createStub(AbstractSchemaManager::class);
        $schemaManager->method('introspectSchema')->willReturn($schema);

        $connection = static::createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $definition->compile($registry);
        $registry->method('getDefinitions')->willReturn([$definition]);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getByClassOrEntityName')->willReturn($definition);

        // No shouldSkipDefinition override needed: the skip regex only matches backslash
        // namespaces, never the file-path-based name of an anonymous definition class.
        $validator = new DefinitionValidator($registry, $connection);

        return array_values(array_filter(
            $validator->validate()[$definition->getClass()] ?? [],
            static fn (string $violation): bool => str_contains($violation, 'has no configured field')
        ));
    }

    /**
     * @param list<string> $dbPrimaryKeys
     */
    private function createValidatorWithTable(EntityDefinition $definition, array $dbPrimaryKeys, EntityDefinition ...$references): DefinitionValidator
    {
        $pkConstraint = null;
        if ($dbPrimaryKeys !== []) {
            $pkColumns = array_map(
                static function (string $col): UnqualifiedName {
                    static::assertNotEmpty($col);

                    return new UnqualifiedName(Identifier::unquoted($col));
                },
                $dbPrimaryKeys
            );
            $pkConstraint = new PrimaryKeyConstraint(null, $pkColumns, false);
        }

        $columns = [
            new Column('id', Type::getType(Types::BINARY)),
            new Column('foo', Type::getType(Types::INTEGER)),
            new Column('created_at', Type::getType(Types::DATETIME_MUTABLE)),
            new Column('updated_at', Type::getType(Types::DATETIME_MUTABLE)),
        ];

        $table = static::createStub(Table::class);
        $table->method('getName')->willReturn('definition_validator_test');
        $table->method('getColumns')->willReturn($columns);
        $table->method('getPrimaryKeyConstraint')->willReturn($pkConstraint);

        $schema = static::createStub(Schema::class);
        $schema->method('hasTable')->willReturn(true);
        $schema->method('getTable')->willReturn($table);
        $schema->method('getTables')->willReturn([$table]);

        $schemaManager = static::createStub(AbstractSchemaManager::class);
        $schemaManager->method('introspectSchema')->willReturn($schema);

        $connection = static::createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $definition->compile($registry);
        $byEntityName = [$definition->getEntityName() => $definition];
        foreach ($references as $reference) {
            $reference->compile($registry);
            $byEntityName[$reference->getEntityName()] = $reference;
        }
        $registry->method('getDefinitions')->willReturn([$definition]);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getByClassOrEntityName')->willReturnCallback(
            static fn (string $key): EntityDefinition => $byEntityName[$key] ?? $references[0] ?? $definition
        );

        // @phpstan-ignore class.extendsFinalByPhpDoc
        return new class($registry, $connection) extends DefinitionValidator {
            protected function shouldSkipDefinition(string $definitionClass): bool
            {
                return false;
            }
        };
    }

    /**
     * @param class-string<Entity> $entityClass
     */
    private function attributeEntityDefinition(string $entityClass): AttributeEntityDefinition
    {
        foreach ((new AttributeEntityCompiler())->compile($entityClass) as $meta) {
            if ($meta['type'] === 'entity') {
                return new AttributeEntityDefinition($meta);
            }
        }

        static::fail('No entity definition compiled for ' . $entityClass);
    }

    private function createAttributeEntityValidator(bool $skipTestDefinitions, AttributeEntityDefinition ...$definitions): DefinitionValidator
    {
        $schema = static::createStub(Schema::class);
        $schema->method('hasTable')->willReturn(false);
        $schema->method('getTables')->willReturn([]);

        $schemaManager = static::createStub(AbstractSchemaManager::class);
        $schemaManager->method('introspectSchema')->willReturn($schema);

        $connection = static::createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        foreach ($definitions as $definition) {
            $definition->compile($registry);
        }
        $registry->method('getDefinitions')->willReturn($definitions);

        if ($skipTestDefinitions) {
            return new DefinitionValidator($registry, $connection);
        }

        // @phpstan-ignore class.extendsFinalByPhpDoc
        return new class($registry, $connection) extends DefinitionValidator {
            protected function shouldSkipDefinition(string $definitionClass): bool
            {
                return false;
            }
        };
    }

    /**
     * @param class-string<Entity> ...$entityClasses
     */
    private function createValidatorWithRegisteredAttributeEntities(string ...$entityClasses): DefinitionValidator
    {
        $definitions = [VersionDefinition::class, VersionCommitDefinition::class, VersionCommitDataDefinition::class];
        foreach ($entityClasses as $entityClass) {
            foreach ((new AttributeEntityCompiler())->compile($entityClass) as $meta) {
                $definitions[$meta['entity_name'] . '.definition'] = $meta['type'] === 'entity'
                    ? new AttributeEntityDefinition($meta)
                    : new AttributeMappingDefinition($meta);
            }
        }

        $registry = new StaticDefinitionInstanceRegistry(
            $definitions,
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $schema = static::createStub(Schema::class);
        $schema->method('hasTable')->willReturn(true);
        $schema->method('getTable')->willReturn(static::createStub(Table::class));

        $schemaManager = static::createStub(AbstractSchemaManager::class);
        $schemaManager->method('introspectSchema')->willReturn($schema);

        $connection = static::createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        // @phpstan-ignore class.extendsFinalByPhpDoc
        return new class($registry, $connection) extends DefinitionValidator {
            protected function shouldSkipDefinition(string $definitionClass): bool
            {
                return false;
            }
        };
    }

    private function createValidatorWithNonExistentTable(EntityDefinition $definition): DefinitionValidator
    {
        $schema = static::createStub(Schema::class);
        $schema->method('hasTable')->willReturn(false);
        $schema->method('getTables')->willReturn([]);

        $schemaManager = static::createStub(AbstractSchemaManager::class);
        $schemaManager->method('introspectSchema')->willReturn($schema);

        $connection = static::createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $definition->compile($registry);
        $registry->method('getDefinitions')->willReturn([$definition]);
        $registry->method('getByEntityName')->willReturn($definition);
        $registry->method('getByClassOrEntityName')->willReturn($definition);

        // @phpstan-ignore class.extendsFinalByPhpDoc
        return new class($registry, $connection) extends DefinitionValidator {
            protected function shouldSkipDefinition(string $definitionClass): bool
            {
                return false;
            }
        };
    }

    /**
     * @param list<string> $columnNames
     * @param list<string> $primaryKeyColumns
     */
    private function createTable(string $name, array $columnNames, array $primaryKeyColumns = []): Table
    {
        $columns = array_map(
            static fn (string $columnName): Column => new Column($columnName, Type::getType(Types::BINARY)),
            $columnNames
        );

        $table = new Table($name, $columns);

        if ($primaryKeyColumns !== []) {
            $pkColumns = array_map(
                static function (string $columnName): UnqualifiedName {
                    static::assertNotEmpty($columnName);

                    return new UnqualifiedName(Identifier::unquoted($columnName));
                },
                $primaryKeyColumns
            );
            $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(null, $pkColumns, false));
        }

        return $table;
    }

    /**
     * @param list<string> $toleratedForeignKeys
     *
     * @return list<string>
     */
    private function getForeignKeyViolations(Schema $schema, array $toleratedForeignKeys = []): array
    {
        $schemaManager = static::createStub(AbstractSchemaManager::class);
        $schemaManager->method('introspectSchema')->willReturn($schema);

        $connection = static::createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([]);
        $registry->method('getByEntityName')->willThrowException(new DefinitionNotFoundException(''));

        $violations = (new DefinitionValidator($registry, $connection))->validate($toleratedForeignKeys);
        $registryViolations = $violations[DefinitionInstanceRegistry::class] ?? [];

        return array_values(array_filter(
            $registryViolations,
            static fn (string $violation): bool => str_contains($violation, 'not a complete PRIMARY or UNIQUE key')
        ));
    }
}

/**
 * @internal
 */
#[EntityAttribute('validated_many_to_many')]
class ValidatedManyToManyEntity extends Entity
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    /**
     * @var array<string, CurrencyEntity>|null
     */
    #[ManyToMany(entity: 'currency')]
    public ?array $currencies = null;
}

/**
 * @internal
 */
#[EntityAttribute('track')]
class TrackEntity extends Entity
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Version]
    public ?string $versionId = null;
}

/**
 * @internal
 */
#[EntityAttribute('playlist')]
class PlaylistEntity extends Entity
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[ForeignKey(entity: 'track')]
    public ?string $coverTrackId = null;

    #[ReferenceVersion(entity: 'track')]
    public ?string $coverTrackVersionId = null;

    #[ManyToOne(entity: 'track')]
    public ?TrackEntity $coverTrack = null;

    /**
     * @var array<string, TrackEntity>|null
     */
    #[ManyToMany(entity: 'track')]
    public ?array $tracks = null;
}

/**
 * @internal
 */
#[EntityAttribute('album')]
class AlbumEntity extends Entity
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[ForeignKey(entity: 'track')]
    public ?string $leadSingleId = null;

    #[ManyToOne(entity: 'track')]
    public ?TrackEntity $leadSingle = null;

    /**
     * @var array<string, TrackEntity>|null
     */
    #[ManyToMany(entity: 'track')]
    public ?array $tracks = null;
}

/**
 * @internal
 */
#[EntityAttribute('flawed_attribute_entity')]
class FlawedAttributeEntity extends Entity
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Field(type: FieldType::STRING, translated: true)]
    public ?string $name = null;

    /**
     * @var array<string, ArrayEntity>|null
     */
    #[Translations]
    public ?array $translations = null;

    #[ForeignKey(entity: 'currency')]
    public ?string $currencyId = null;

    #[OneToOne(entity: 'currency')]
    public ?CurrencyEntity $currency = null;

    /**
     * @var array<string, CurrencyEntity>|null
     */
    #[ManyToMany(entity: 'currency')]
    public ?array $currencyList = null;

    public readonly string $readonlyProperty;

    public function __construct()
    {
        $this->readonlyProperty = 'not a field';
    }

    /**
     * @param array<string, CurrencyEntity>|null $currencyList
     */
    public function setCurrencyList(?array $currencyList): void
    {
        $this->currencyList = $currencyList;
    }
}

/**
 * @internal
 */
#[EntityAttribute('scorecard')]
class ScorecardEntity extends Entity
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Version]
    public ?string $versionId = null;

    /**
     * @var array<string, CriterionEntity>|null
     */
    #[OneToMany(entity: 'criterion', ref: 'scorecard_id')]
    public ?array $criteria = null;

    /**
     * @var array<string, CriterionEntity>|null
     */
    #[OneToMany(entity: 'criterion', ref: 'scorecard_id')]
    public ?array $criterion = null;

    /**
     * @var array<string, CriterionEntity>|null
     */
    #[ManyToMany(entity: 'criterion', mapping: 'scorecard_shared_criterion')]
    public ?array $sharedCriteria = null;

    /**
     * @var array<string, CriterionEntity>|null
     */
    #[ManyToMany(entity: 'criterion')]
    public ?array $criterionPool = null;
}

/**
 * @internal
 */
#[EntityAttribute('criterion')]
class CriterionEntity extends Entity
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Version]
    public ?string $versionId = null;

    #[ForeignKey(entity: 'scorecard')]
    public ?string $scorecardId = null;

    #[ManyToOne(entity: 'scorecard')]
    public ?ScorecardEntity $scorecard = null;

    #[ForeignKey(entity: 'criterion')]
    public ?string $parentId = null;

    #[ReferenceVersion(entity: 'criterion')]
    public ?string $parentVersionId = null;

    #[ManyToOne(entity: 'criterion')]
    public ?CriterionEntity $parent = null;
}
