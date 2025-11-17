<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\TestDefinition\DefinitionValidatorTestDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\TestDefinition\DefinitionValidatorWithNonStorageAwarePrimaryKeyTestDefinition;

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
        $definition = new DefinitionValidatorTestDefinition();
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

    public function testPrimaryKeyMatchReportsNoViolation(): void
    {
        $definition = new DefinitionValidatorTestDefinition();
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

    public function testPrimaryKeyValidationHandlesTableNotFoundException(): void
    {
        $definition = new DefinitionValidatorTestDefinition();
        $validator = $this->createValidatorWithNonExistentTable($definition);

        $violations = $validator->validate();
        $definitionViolations = $violations[$definition::class] ?? [];

        // Filter to only primary key violations
        $primaryKeyViolations = array_filter(
            $definitionViolations,
            static fn (string $violation): bool => str_contains($violation, 'Primary key mismatch')
        );

        // When table doesn't exist, introspectTable throws an exception which is caught,
        // and validatePrimaryKeyConsistency returns empty array (no violations)
        static::assertEmpty($primaryKeyViolations, 'Expected no primary key violations when table does not exist, but got: ' . implode(', ', $primaryKeyViolations));
    }

    public function testPrimaryKeyValidationSkipsNonStorageAwareFields(): void
    {
        // Use a definition with a non-StorageAware field marked as PrimaryKey
        $definition = new DefinitionValidatorWithNonStorageAwarePrimaryKeyTestDefinition();
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

    /**
     * @param list<string> $dbPrimaryKeys
     */
    private function createValidatorWithTable(DefinitionValidatorTestDefinition $definition, array $dbPrimaryKeys): DefinitionValidator
    {
        $connection = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $table = $this->createMock(Table::class);
        $table->method('getName')->willReturn('definition_validator_test');
        $table->method('getColumns')->willReturn([]);

        $pkConstraint = null;
        if (!empty($dbPrimaryKeys)) {
            $pkColumns = array_map(
                static function (string $col): UnqualifiedName {
                    \assert($col !== '');

                    return new UnqualifiedName(Identifier::unquoted($col));
                },
                $dbPrimaryKeys
            );
            $pkConstraint = new PrimaryKeyConstraint(null, $pkColumns, false);
        }

        // This setup is to make the other validation checks pass
        $columns = [
            new Column('id', Type::getType(Types::BINARY)),
            new Column('foo', Type::getType(Types::INTEGER)),
            new Column('created_at', Type::getType(Types::DATETIME_MUTABLE)),
            new Column('updated_at', Type::getType(Types::DATETIME_MUTABLE)),
        ];
        $schemaManager->method('listTableColumns')->willReturn($columns);
        $table->method('getPrimaryKeyConstraint')->willReturn($pkConstraint);
        $schemaManager->method('introspectTable')->willReturn($table);
        $schemaManager->method('listTables')->willReturn([$table]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $definition->compile($registry);
        $registry->method('getDefinitions')->willReturn([$definition]);
        $registry->method('getByEntityName')->willReturn($definition);

        return new DefinitionValidator($registry, $connection);
    }

    private function createValidatorWithNonExistentTable(DefinitionValidatorTestDefinition $definition): DefinitionValidator
    {
        $connection = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $schemaManager->method('introspectTable')->willThrowException(new \Exception('Table does not exist'));

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $definition->compile($registry);
        $registry->method('getDefinitions')->willReturn([$definition]);
        $registry->method('getByEntityName')->willReturn($definition);

        return new DefinitionValidator($registry, $connection);
    }
}
