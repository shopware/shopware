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
}
