<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\Comparator;
use Doctrine\DBAL\Platforms\MySQL\DefaultTableOptions;
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
        $platform = new MySQLPlatform();

        $this->schemaBuilder = $this->createMock(SchemaBuilder::class);
        $this->schemaManager = $this->createMock(MySQLSchemaManager::class);

        $charsetMetadataProvider = $this->createMock(CharsetMetadataProvider::class);
        $charsetMetadataProvider->method('getDefaultCharsetCollation')
            ->willReturn('utf8mb4_unicode_ci');

        $collationMetadataProvider = $this->createMock(CollationMetadataProvider::class);
        $collationMetadataProvider->method('getCollationCharset')
            ->willReturn('utf8mb4');
        $this->schemaManager->method('createComparator')->willReturn(new Comparator(
            $platform,
            $charsetMetadataProvider,
            $collationMetadataProvider,
            new DefaultTableOptions('utf8mb4', 'utf8mb4_unicode_ci'),
        ));

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $connection->method('getDatabasePlatform')->willReturn($platform);

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
        static::assertStringContainsString('ALTER TABLE test ADD priority INT NOT NULL, ADD test2_id VARCHAR(255) NOT NULL', $queries[0]);
        static::assertStringContainsString('ALTER TABLE test ADD CONSTRAINT fk_column_id FOREIGN KEY (test2_id) REFERENCES test2 (id)', $queries[1]);
    }

    public function testGenerateQueriesForNewTable(): void
    {
        $entityDefinition = $this->createMock(EntityDefinition::class);

        $this->schemaManager->method('tablesExist')->willReturn(false);

        $this->schemaBuilder->method('buildSchemaOfDefinition')->willReturn($this->getNewTable());

        $queries = $this->generator->generateQueries($entityDefinition);

        static::assertCount(2, $queries);
        static::assertStringContainsString('CREATE TABLE test (id VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, priority INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, test2_id VARCHAR(255) NOT NULL, PRIMARY KEY(id))', $queries[0]);
        static::assertStringContainsString('ALTER TABLE test ADD CONSTRAINT fk_column_id FOREIGN KEY (test2_id) REFERENCES test2 (id)', $queries[1]);
    }

    private function getOriginalTable(): Table
    {
        $table = new Table('test');

        $table->addColumn('id', 'string', ['length' => 255]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('created_at', 'datetime');
        $table->addColumn('updated_at', 'datetime');

        $table->setPrimaryKey(['id']);

        $table->addIndex(['name']);

        return $table;
    }

    private function getNewTable(): Table
    {
        $table = new Table('test');

        $table->addColumn('id', 'string', ['length' => 255]);
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addColumn('priority', 'integer');
        $table->addColumn('created_at', 'datetime');
        $table->addColumn('updated_at', 'datetime');
        $table->addColumn('test2_id', 'string', ['length' => 255]);

        $table->addForeignKeyConstraint('test2', ['test2_id'], ['id'], [], 'fk_column_id');
        $table->setPrimaryKey(['id']);

        $table->addIndex(['priority']);

        return $table;
    }
}
