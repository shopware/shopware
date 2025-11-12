<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
#[CoversClass(DefinitionValidator::class)]
class DefinitionValidatorTest extends TestCase
{
    private static ?Connection $connection = null;

    public static function setUpBeforeClass(): void
    {
        if (!\extension_loaded('pdo_sqlite')) {
            static::markTestSkipped('This test requires the pdo_sqlite extension');
        }

        self::$connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => ':memory:',
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$connection === null) {
            return;
        }

        self::$connection->close();
        self::$connection = null;
    }

    protected function tearDown(): void
    {
        if (self::$connection === null) {
            return;
        }

        self::$connection->executeStatement('DROP TABLE IF EXISTS definition_validator_test');
    }

    /**
     * @param list<string> $expectedMessages
     */
    #[DataProvider('primaryKeyConsistencyProvider')]
    public function testPrimaryKeyConsistency(?string $tableSql, array $expectedMessages): void
    {
        static::assertNotNull(self::$connection);

        if ($tableSql) {
            self::$connection->executeStatement($tableSql);
        }

        $definition = $this->getTestDefinition();

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $definition->compile($registry);
        $validator = new DefinitionValidator($registry, self::$connection);

        $method = new \ReflectionMethod(DefinitionValidator::class, 'validatePrimaryKeyConsistency');
        $violations = $method->invoke($validator, $definition);

        if (empty($expectedMessages)) {
            if (isset($violations[$definition::class])) {
                static::assertCount(0, $violations[$definition::class]);
            } else {
                static::assertEmpty($violations);
            }

            return;
        }

        static::assertCount(1, $violations);
        static::assertArrayHasKey($definition::class, $violations);
        static::assertCount(1, $violations[$definition::class]);

        foreach ($expectedMessages as $expectedMessage) {
            static::assertStringContainsString($expectedMessage, $violations[$definition::class][0]);
        }
    }

    /**
     * @return \Generator<string, array{string|null, list<string>}>
     */
    public static function primaryKeyConsistencyProvider(): \Generator
    {
        yield 'mismatched primary key' => [
            'CREATE TABLE definition_validator_test (id BLOB NOT NULL, foo INT NOT NULL, PRIMARY KEY(foo));',
            [
                'Primary key mismatch in entity "definition_validator_test"',
                'Table has PRIMARY KEY (foo)',
                'entity definition has PrimaryKey flags on (id)',
            ],
        ];

        yield 'matching primary key' => [
            'CREATE TABLE definition_validator_test (id BLOB NOT NULL, foo INT NOT NULL, PRIMARY KEY(id));',
            [],
        ];

        yield 'table does not exist' => [
            null,
            [],
        ];
    }

    private function getTestDefinition(): DefinitionValidatorTestDefinition
    {
        // @phpstan-ignore-next-line
        return new DefinitionValidatorTestDefinition();
    }
}

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
