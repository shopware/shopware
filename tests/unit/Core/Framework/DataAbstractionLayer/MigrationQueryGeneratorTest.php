<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\MigrationQueryGenerator;

/**
 * @internal
 */
#[CoversClass(MigrationQueryGenerator::class)]
class MigrationQueryGeneratorTest extends TestCase
{
    private SchemaBuilder&MockObject $schemaBuilder;

    private MySQLSchemaManager&MockObject $schemaManager;

    private MigrationQueryGenerator $generator;

    protected function setUp(): void
    {
        $this->schemaBuilder = $this->createMock(SchemaBuilder::class);
        $this->schemaManager = $this->createMock(MySQLSchemaManager::class);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());

        $this->generator = new MigrationQueryGenerator($connection, $this->schemaBuilder);
    }

    public function testGenerateQueriesForExistingTable(): void
    {
        $entityDefinition = $this->createMock(EntityDefinition::class);

        $this->schemaManager->method('tablesExist')->willReturn(true);

        $this->schemaManager->method('introspectTable')->willReturn($this->getOriginalTable());

        $this->schemaBuilder->method('buildSchemaOfDefinition')->willReturn($this->getNewTable());

        $queries = $this->generator->generateQueries($entityDefinition);

        static::assertCount(2, $queries);
        static::assertStringContainsString('ALTER TABLE test ADD test2_id VARCHAR(255) NOT NULL, ADD priority INT NOT NULL', $queries[0]);
        static::assertStringContainsString('ALTER TABLE test ADD CONSTRAINT fk_column_id FOREIGN KEY (test2_id) REFERENCES test2 (id)', $queries[1]);
    }

    public function testGenerateQueriesForNewTable(): void
    {
        $entityDefinition = $this->createMock(EntityDefinition::class);

        $this->schemaManager->method('tablesExist')->willReturn(false);

        $this->schemaBuilder->method('buildSchemaOfDefinition')->willReturn($this->getNewTable());

        $queries = $this->generator->generateQueries($entityDefinition);

        static::assertCount(2, $queries);
        static::assertStringContainsString('CREATE TABLE test (id VARCHAR(255) NOT NULL, test2_id VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, priority INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB', $queries[0]);
        static::assertStringContainsString('ALTER TABLE test ADD CONSTRAINT fk_column_id FOREIGN KEY (test2_id) REFERENCES test2 (id)', $queries[1]);
    }

    private function getOriginalTable(): Table
    {
        $table = new Table('test');

        $table->addColumn('id', 'string');
        $table->addColumn('name', 'string');
        $table->addColumn('created_at', 'datetime');
        $table->addColumn('updated_at', 'datetime');

        $table->setPrimaryKey(['id']);

        $table->addIndex(['name']);

        return $table;
    }

    private function getNewTable(): Table
    {
        $table = new Table('test');

        $table->addColumn('id', 'string');
        $table->addColumn('name', 'string');
        $table->addColumn('priority', 'integer');
        $table->addColumn('created_at', 'datetime');
        $table->addColumn('updated_at', 'datetime');
        $table->addColumn('test2_id', 'string');

        $table->addForeignKeyConstraint('test2', ['test2_id'], ['id'], [], 'fk_column_id');
        $table->setPrimaryKey(['id']);

        $table->addIndex(['priority']);

        return $table;
    }
}
