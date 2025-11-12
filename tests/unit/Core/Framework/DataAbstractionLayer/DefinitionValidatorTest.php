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

/**
 * @internal
 */
#[CoversClass(DefinitionValidator::class)]
class DefinitionValidatorTest extends TestCase
{
    /**
     * @param list<string> $expectedMessages
     * @param list<string>|null $dbPrimaryKeys
     */
    #[DataProvider('primaryKeyConsistencyProvider')]
    public function testPrimaryKeyConsistency(?array $dbPrimaryKeys, array $expectedMessages): void
    {
        $definition = $this->getTestDefinition();

        $connection = $this->createMock(Connection::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        if ($dbPrimaryKeys === null) {
            $schemaManager->method('introspectTable')->willThrowException(new \Exception('Table does not exist'));
        } else {
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
            ];
            $schemaManager->method('listTableColumns')->willReturn($columns);
            $table->method('getPrimaryKeyConstraint')->willReturn($pkConstraint);
            $schemaManager->method('introspectTable')->willReturn($table);
            $schemaManager->method('listTables')->willReturn([$table]);
        }

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $definition->compile($registry);
        $registry->method('getDefinitions')->willReturn([$definition]);
        $registry->method('getByEntityName')->willReturn($definition);

        $validator = new DefinitionValidator($registry, $connection);
        $violations = $validator->validate();

        $definitionViolations = $violations[$definition::class] ?? [];

        // Filter to only primary key violations
        $primaryKeyViolations = array_filter(
            $definitionViolations,
            static fn (string $violation): bool => str_contains($violation, 'Primary key mismatch')
        );

        if (empty($expectedMessages)) {
            static::assertEmpty($primaryKeyViolations, 'Expected no primary key violations, but got: ' . implode(', ', $primaryKeyViolations));

            return;
        }

        static::assertCount(1, $primaryKeyViolations, 'Expected 1 primary key violation, but got: ' . implode(', ', $primaryKeyViolations));
        $violation = reset($primaryKeyViolations);

        foreach ($expectedMessages as $expectedMessage) {
            static::assertStringContainsString($expectedMessage, $violation);
        }
    }

    /**
     * @return \Generator<string, array{list<string>|null, list<string>}>
     */
    public static function primaryKeyConsistencyProvider(): \Generator
    {
        yield 'mismatched primary key' => [
            ['foo'],
            [
                'Primary key mismatch in entity "definition_validator_test"',
                'Table has PRIMARY KEY (foo)',
                'entity definition has PrimaryKey flags on (id)',
            ],
        ];

        yield 'matching primary key' => [
            ['id'],
            [],
        ];

        yield 'no primary key' => [
            [],
            [
                'Primary key mismatch in entity "definition_validator_test"',
                'Table has PRIMARY KEY ()',
                'entity definition has PrimaryKey flags on (id)',
            ],
        ];

        yield 'table does not exist' => [
            null,
            [],
        ];
    }

    private function getTestDefinition(): \Shopware\Core\Framework\DataAbstractionLayer\Validation\DefinitionValidatorTestDefinition
    {
        return new \Shopware\Core\Framework\DataAbstractionLayer\Validation\DefinitionValidatorTestDefinition();
    }
}

namespace Shopware\Core\Framework\DataAbstractionLayer\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class DefinitionValidatorTestDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'definition_validator_test';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            new IntField('foo', 'foo'),
        ]);
    }
}
