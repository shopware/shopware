<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Command\FulltextSearchCommand;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\FulltextSearchRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\Command\FulltextSearchCommand
 */
class FulltextSearchCommandTest extends TestCase
{
    private MockObject&DefinitionInstanceRegistry $definitionRegistry;
    private MockObject&Connection $connection;
    private MockObject&FulltextSearchRegistry $fulltextRegistry;
    private FulltextSearchCommand $command;

    protected function setUp(): void
    {
        $this->definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->connection = $this->createMock(Connection::class);
        $this->fulltextRegistry = $this->createMock(FulltextSearchRegistry::class);

        $this->command = new FulltextSearchCommand(
            $this->definitionRegistry,
            $this->connection,
            $this->fulltextRegistry
        );
    }

    public function testCommandFailsForNonMySQLPlatform(): void
    {
        $platform = $this->createMock(\Doctrine\DBAL\Platforms\PostgreSQLPlatform::class);
        $this->connection->method('getDatabasePlatform')->willReturn($platform);

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $result = $this->command->run($input, $output);

        static::assertEquals(Command::FAILURE, $result);
    }

    public function testCommandSucceedsForMySQLPlatform(): void
    {
        $platform = $this->createMock(MySQLPlatform::class);
        $this->connection->method('getDatabasePlatform')->willReturn($platform);

        // Mock an entity definition with searchable string field
        $definition = $this->createMock(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('test_entity');

        $searchRanking = new SearchRanking(100);
        $stringField = $this->createMock(StringField::class);
        $stringField->method('getPropertyName')->willReturn('name');
        $stringField->method('getStorageName')->willReturn('name');
        $stringField->method('is')->with(SearchRanking::class)->willReturn(true);

        $fields = new FieldCollection([$stringField]);
        $definition->method('getFields')->willReturn($fields);

        $this->definitionRegistry->method('getDefinitions')->willReturn([$definition]);

        // Mock schema manager to simulate no existing fulltext index
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $table = $this->createMock(Table::class);
        $table->method('getIndexes')->willReturn([]);

        $schemaManager->method('tablesExist')->willReturn(true);
        $schemaManager->method('introspectTable')->willReturn($table);
        $this->connection->method('createSchemaManager')->willReturn($schemaManager);

        // Mock successful index creation
        $this->connection->expects(static::once())
            ->method('executeStatement')
            ->with(static::stringContains('ALTER TABLE `test_entity` ADD FULLTEXT KEY'));

        // Mock registry operations
        $this->fulltextRegistry->expects(static::once())
            ->method('addIndexedFields')
            ->with(['test_entity.name']);

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        // Mock user confirmation
        $input->method('isInteractive')->willReturn(false);

        $result = $this->command->run($input, $output);

        static::assertEquals(Command::SUCCESS, $result);
    }

    public function testCommandSkipsFieldsWithExistingFulltextIndex(): void
    {
        $platform = $this->createMock(MySQLPlatform::class);
        $this->connection->method('getDatabasePlatform')->willReturn($platform);

        $definition = $this->createMock(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('test_entity');

        $stringField = $this->createMock(StringField::class);
        $stringField->method('getPropertyName')->willReturn('name');
        $stringField->method('getStorageName')->willReturn('name');
        $stringField->method('is')->with(SearchRanking::class)->willReturn(true);

        $fields = new FieldCollection([$stringField]);
        $definition->method('getFields')->willReturn($fields);

        $this->definitionRegistry->method('getDefinitions')->willReturn([$definition]);

        // Mock schema manager to simulate existing fulltext index
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $table = $this->createMock(Table::class);
        
        $index = $this->createMock(\Doctrine\DBAL\Schema\Index::class);
        $index->method('hasFlag')->with('fulltext')->willReturn(true);
        $index->method('getColumns')->willReturn(['name']);
        
        $table->method('getIndexes')->willReturn([$index]);

        $schemaManager->method('tablesExist')->willReturn(true);
        $schemaManager->method('introspectTable')->willReturn($table);
        $this->connection->method('createSchemaManager')->willReturn($schemaManager);

        // Should not execute any ALTER statements since index already exists
        $this->connection->expects(static::never())->method('executeStatement');
        $this->fulltextRegistry->expects(static::never())->method('addIndexedFields');

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $input->method('isInteractive')->willReturn(false);

        $result = $this->command->run($input, $output);

        static::assertEquals(Command::SUCCESS, $result);
    }
} 