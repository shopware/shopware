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
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Validation\Fixtures\DefinitionStub;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Validation\Fixtures\DefinitionWithNonStorageAwarePrimaryKeyStub;

/**
 * @internal
 */
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
        $validator = new DefinitionValidator(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(Connection::class)
        );
        $method = new \ReflectionMethod(DefinitionValidator::class, 'isIgnoredField');

        Feature::fake([], static function () use ($method, $validator, $key): void {
            static::assertTrue($method->invoke($validator, $key));
        });

        Feature::fake(['v6.8.0.0'], static function () use ($method, $validator, $key): void {
            static::assertFalse($method->invoke($validator, $key));
        });
    }

    /**
     * @deprecated tag:v6.8.0 - should be removed when FEATURE_GATED_IGNORE_FIELDS is cleared
     */
    public function testIgnoreFieldsAreStillIgnoredWithFeatureActive(): void
    {
        $validator = new DefinitionValidator(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(Connection::class)
        );
        $method = new \ReflectionMethod(DefinitionValidator::class, 'isIgnoredField');

        Feature::fake(['v6.8.0.0'], static function () use ($method, $validator): void {
            static::assertTrue($method->invoke($validator, 'product.cover'));
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

    /**
     * @param list<string> $dbPrimaryKeys
     */
    private function createValidatorWithTable(EntityDefinition $definition, array $dbPrimaryKeys): DefinitionValidator
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

        $table = $this->createMock(Table::class);
        $table->method('getName')->willReturn('definition_validator_test');
        $table->method('getColumns')->willReturn($columns);
        $table->method('getPrimaryKeyConstraint')->willReturn($pkConstraint);

        $schema = $this->createMock(Schema::class);
        $schema->method('hasTable')->willReturn(true);
        $schema->method('getTable')->willReturn($table);
        $schema->method('getTables')->willReturn([$table]);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('introspectSchema')->willReturn($schema);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $definition->compile($registry);
        $registry->method('getDefinitions')->willReturn([$definition]);
        $registry->method('getByEntityName')->willReturn($definition);

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
        $schema = $this->createMock(Schema::class);
        $schema->method('hasTable')->willReturn(false);
        $schema->method('getTables')->willReturn([]);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('introspectSchema')->willReturn($schema);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $definition->compile($registry);
        $registry->method('getDefinitions')->willReturn([$definition]);
        $registry->method('getByEntityName')->willReturn($definition);

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
