<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MakeVersionableMigrationHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use function sprintf;

/**
 * @internal
 *
 * @group slow
 */
class DynamicPrimaryKeyChangeTest extends TestCase
{
    use KernelTestBehaviour;

    public function testPrimaryKeyExistsEverywhere(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $schemaManager = $connection->createSchemaManager();

        $tables = $schemaManager->listTableNames();

        foreach ($tables as $tableName) {
            $indexes = $schemaManager->listTableIndexes($tableName);

            static::assertArrayHasKey('primary', $indexes);
        }
    }

    public function testFullConversionAgainstFixtures(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $this->importFixtureSchema();
        $schemaManager = $connection->createSchemaManager();

        $tableName = '_dpkc_main';

        $playbookGenerator = new MakeVersionableMigrationHelper($connection);

        $hydratedData = $playbookGenerator->getRelationData($tableName, 'id');
        $playbook = $playbookGenerator->createSql($hydratedData, $tableName, 'mission_id', Uuid::randomHex());

        foreach ($this->getExpectationsBefore() as $tableName => $expectation) {
            $indexes = $schemaManager->listTableIndexes($tableName);
            $foreignKeys = $schemaManager->listTableForeignKeys($tableName);
            $columns = $schemaManager->listTableColumns($tableName);

            static::assertCount($expectation['indexes'], $indexes, print_r($indexes, true) . ' index on ' . $tableName);
            static::assertCount($expectation['foreignKeys'], $foreignKeys, print_r($foreignKeys, true) . ' foreignKey on ' . $tableName);
            static::assertCount($expectation['columns'], $columns, print_r($columns, true) . ' columns on ' . $tableName);
        }

        foreach ($playbook as $query) {
            $connection->executeStatement($query);
        }

        foreach ($this->getExpectationsAfter() as $tableName => $expectation) {
            $indexes = $schemaManager->listTableIndexes($tableName);
            $foreignKeys = $schemaManager->listTableForeignKeys($tableName);
            $columns = $schemaManager->listTableColumns($tableName);

            static::assertCount($expectation['indexes'], $indexes, print_r($indexes, true) . ' index on ' . $tableName);
            static::assertCount($expectation['foreignKeys'], $foreignKeys, print_r($foreignKeys, true) . ' foreignKey on ' . $tableName);
            static::assertCount($expectation['columns'], $columns, print_r($columns, true) . ' columns on ' . $tableName);

            switch ($tableName) {
                case '_dpkc_main_translation':
                    static::assertSame(['_dpkc_main_id', 'language_id', '_dpkc_main_mission_id'], $indexes['primary']->getColumns());

                    break;
            }
        }

        $this->importAfterChangeFixtures();
    }

    /**
     * @after
     */
    public function cleanupTables(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        $tables = [
            '_dpkc_main',
            '_dpkc_main_translation',
            '_dpkc_1n_relation1',
            '_dpkc_1n_relation2',
            '_dpkc_other',
            '_dpkc_other_multi_pk',
            '_dpkc_mn_relation1',
            '_dpkc_mn_relation2',
            '_dpkc_mn_relation_multi_pk',
            '_dpkc_1n_multi_relation',
            '_dpkc_1n_relation_on_another_id',
            '_dpkc_1n_relation_double_constraint',
            '_dpkc_1n_relation3',
            '_dpkc_1n_relation_double_constraint_two',
        ];

        foreach ($tables as $table) {
            $connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $table));
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function importFixtureSchema(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $fixture = file_get_contents(__DIR__ . '/_dynamicPrimaryKeyChange.sql');
        static::assertIsString($fixture);

        foreach (array_filter(array_map('trim', explode(';', $fixture))) as $stmt) {
            $connection->executeStatement($stmt);
        }
    }

    private function importAfterChangeFixtures(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $fixture = file_get_contents(__DIR__ . '/_dynamicPrimaryKeyChangeAfterWithoutAdditionalColumn.sql');
        $fixture .= file_get_contents(__DIR__ . '/_dynamicPrimaryKeyChangeAfterWithAdditionalColumn.sql');

        foreach (array_filter(array_map('trim', explode(';', $fixture))) as $stmt) {
            $connection->executeStatement($stmt);
        }
    }

    /**
     * @return int[][]
     */
    private function getExpectationsAfter(): array
    {
        return [
            '_dpkc_main' => [
                'indexes' => 4,
                'foreignKeys' => 0,
                'columns' => 5,
            ],
            '_dpkc_main_translation' => [
                'indexes' => 3,
                'foreignKeys' => 2,
                'columns' => 4,
            ],
            '_dpkc_1n_relation1' => [
                'indexes' => 2,
                'foreignKeys' => 1,
                'columns' => 4,
            ],
            '_dpkc_1n_relation2' => [
                'indexes' => 2,
                'foreignKeys' => 1,
                'columns' => 4,
            ],
            '_dpkc_other' => [
                'indexes' => 1,
                'foreignKeys' => 0,
                'columns' => 2,
            ],
            '_dpkc_other_multi_pk' => [
                'indexes' => 1,
                'foreignKeys' => 0,
                'columns' => 3,
            ],
            '_dpkc_mn_relation1' => [
                'indexes' => 3,
                'foreignKeys' => 2,
                'columns' => 3,
            ],
            '_dpkc_mn_relation2' => [
                'indexes' => 3,
                'foreignKeys' => 2,
                'columns' => 3,
            ],
            '_dpkc_mn_relation_multi_pk' => [
                'indexes' => 3,
                'foreignKeys' => 2,
                'columns' => 4,
            ],
            '_dpkc_1n_multi_relation' => [
                'indexes' => 3,
                'foreignKeys' => 2,
                'columns' => 6,
            ],
            '_dpkc_1n_relation_on_another_id' => [
                'indexes' => 2,
                'foreignKeys' => 1,
                'columns' => 3,
            ],
            '_dpkc_1n_relation_double_constraint' => [
                'indexes' => 4,
                'foreignKeys' => 1,
                'columns' => 5,
            ],
        ];
    }

    /**
     * @return int[][]
     */
    private function getExpectationsBefore(): array
    {
        return [
            '_dpkc_main' => [
                'indexes' => 3,
                'foreignKeys' => 0,
                'columns' => 4,
            ],
            '_dpkc_main_translation' => [
                'indexes' => 2,
                'foreignKeys' => 2,
                'columns' => 3,
            ],
            '_dpkc_1n_relation1' => [
                'indexes' => 2,
                'foreignKeys' => 1,
                'columns' => 3,
            ],
            '_dpkc_1n_relation2' => [
                'indexes' => 2,
                'foreignKeys' => 1,
                'columns' => 3,
            ],
            '_dpkc_other' => [
                'indexes' => 1,
                'foreignKeys' => 0,
                'columns' => 2,
            ],
            '_dpkc_other_multi_pk' => [
                'indexes' => 1,
                'foreignKeys' => 0,
                'columns' => 3,
            ],
            '_dpkc_mn_relation1' => [
                'indexes' => 2,
                'foreignKeys' => 2,
                'columns' => 2,
            ],
            '_dpkc_mn_relation2' => [
                'indexes' => 2,
                'foreignKeys' => 2,
                'columns' => 2,
            ],
            '_dpkc_mn_relation_multi_pk' => [
                'indexes' => 2,
                'foreignKeys' => 2,
                'columns' => 3,
            ],
            '_dpkc_1n_multi_relation' => [
                'indexes' => 3,
                'foreignKeys' => 2,
                'columns' => 4,
            ],
            '_dpkc_1n_relation_on_another_id' => [
                'indexes' => 2,
                'foreignKeys' => 1,
                'columns' => 3,
            ],
            '_dpkc_1n_relation_double_constraint' => [
                'indexes' => 4,
                'foreignKeys' => 1,
                'columns' => 4,
            ],
            '_dpkc_1n_relation_double_constraint_two' => [
                'indexes' => 4,
                'foreignKeys' => 1,
                'columns' => 4,
            ],
        ];
    }
}
