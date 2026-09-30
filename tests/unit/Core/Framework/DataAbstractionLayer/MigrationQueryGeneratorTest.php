<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\Comparator;
use Doctrine\DBAL\Platforms\MySQL\DefaultTableOptions;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\MigrationQueryGenerator;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MigrationQueryGenerator::class)]
class MigrationQueryGeneratorTest extends TestCase
{
    private SchemaBuilder&Stub $schemaBuilder;

    private MySQLSchemaManager&Stub $schemaManager;

    private MigrationQueryGenerator $generator;

    protected function setUp(): void
    {
        $platform = new MySQLPlatform();

        $this->schemaBuilder = static::createStub(SchemaBuilder::class);
        $this->schemaManager = static::createStub(MySQLSchemaManager::class);

        $charsetMetadataProvider = static::createStub(CharsetMetadataProvider::class);
        $charsetMetadataProvider->method('getDefaultCharsetCollation')
            ->willReturn('utf8mb4_unicode_ci');

        $collationMetadataProvider = static::createStub(CollationMetadataProvider::class);
        $collationMetadataProvider->method('getCollationCharset')
            ->willReturn('utf8mb4');
        $this->schemaManager->method('createComparator')->willReturn(new Comparator(
            $platform,
            $charsetMetadataProvider,
            $collationMetadataProvider,
            new DefaultTableOptions('utf8mb4', 'utf8mb4_unicode_ci'),
        ));

        $connection = static::createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($this->schemaManager);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $this->generator = new MigrationQueryGenerator($connection, $this->schemaBuilder);
    }

    public function testGenerateQueriesForExistingTable(): void
    {
        $entityDefinition = static::createStub(EntityDefinition::class);

        $this->schemaManager->method('tableExists')->willReturn(true);

        $this->schemaManager->method('introspectTableByUnquotedName')->willReturn($this->getOriginalTable());

        $this->schemaBuilder->method('buildSchemaOfDefinition')->willReturn($this->getNewTable());

        $queries = $this->generator->generateQueries($entityDefinition);

        static::assertCount(2, $queries);
        static::assertStringContainsString('ALTER TABLE test ADD priority INT NOT NULL, ADD test2_id VARCHAR(255) NOT NULL', $queries[0]);
        static::assertStringContainsString('ALTER TABLE test ADD CONSTRAINT fk_column_id FOREIGN KEY (test2_id) REFERENCES test2 (id)', $queries[1]);
    }

    public function testGenerateQueriesForNewTable(): void
    {
        $entityDefinition = static::createStub(EntityDefinition::class);

        $this->schemaManager->method('tablesExist')->willReturn(false);

        $this->schemaBuilder->method('buildSchemaOfDefinition')->willReturn($this->getNewTable());

        $queries = $this->generator->generateQueries($entityDefinition);

        static::assertCount(2, $queries);
        static::assertStringContainsString('CREATE TABLE test (id VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, priority INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, test2_id VARCHAR(255) NOT NULL, PRIMARY KEY (`id`))', $queries[0]);
        static::assertStringContainsString('ALTER TABLE test ADD CONSTRAINT fk_column_id FOREIGN KEY (test2_id) REFERENCES test2 (id)', $queries[1]);
    }

    private function getOriginalTable(): Table
    {
        $table = Table::editor()->setUnquotedName('test');

        $table->addColumn(Column::editor()->setUnquotedName('id')->setTypeName('string')->setLength(255)->create());
        $table->addColumn(Column::editor()->setUnquotedName('name')->setTypeName('string')->setLength(255)->create());
        $table->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName('datetime')->create());
        $table->addColumn(Column::editor()->setUnquotedName('updated_at')->setTypeName('datetime')->create());

        $pk = PrimaryKeyConstraint::editor();
        $pk->setQuotedColumnNames('id');
        $table->addPrimaryKeyConstraint($pk->create());

        $table->addIndex(Index::editor()->addUnquotedColumnName('name'));

        return $table->create();
    }

    private function getNewTable(): Table
    {
        $table = Table::editor()->setUnquotedName('test');

        $table->addColumn(Column::editor()->setUnquotedName('id')->setTypeName('string')->setLength(255)->create());
        $table->addColumn(Column::editor()->setUnquotedName('name')->setTypeName('string')->setLength(255)->create());
        $table->addColumn(Column::editor()->setUnquotedName('priority')->setTypeName('integer')->create());
        $table->addColumn(Column::editor()->setUnquotedName('created_at')->setTypeName('datetime')->create());
        $table->addColumn(Column::editor()->setUnquotedName('updated_at')->setTypeName('datetime')->create());
        $table->addColumn(Column::editor()->setUnquotedName('test2_id')->setTypeName('string')->setLength(255)->create());

        $table->addForeignKeyConstraint(ForeignKeyConstraint::editor()
            ->setUnquotedName('fk_column_id')
            ->setUnquotedReferencingColumnNames('test2_id')
            ->setUnquotedReferencedTableName('test2')
            ->setUnquotedReferencedColumnNames('id')
            ->create());
        $pk = PrimaryKeyConstraint::editor();
        $pk->setQuotedColumnNames('id');
        $table->addPrimaryKeyConstraint($pk->create());

        $table->addIndex(Index::editor()->addUnquotedColumnName('priority'));

        return $table->create();
    }
}
