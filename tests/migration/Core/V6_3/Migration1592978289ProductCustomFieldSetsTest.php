<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCustomFieldSet\ProductCustomFieldSetDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1592978289ProductCustomFieldSets;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1592978289ProductCustomFieldSets
 *
 * @phpstan-type DbColumn array{name: string, type: Type, notnull: bool}
 */
class Migration1592978289ProductCustomFieldSetsTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    /**
     * @before
     */
    public function initialise(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1592978289ProductCustomFieldSets();

        $connection->rollBack();

        if ($this->hasCustomFieldSetColumn($connection, 'product')) {
            $connection->executeStatement('ALTER TABLE `product` DROP COLUMN `customFieldSets`;');
        }

        if ($this->hasGlobalColumn($connection, 'custom_field_set')) {
            $connection->executeStatement('ALTER TABLE `custom_field_set` DROP COLUMN `global`;');
        }

        $connection->executeStatement('DROP TABLE IF EXISTS `product_custom_field_set`;');

        $migration->update($connection);

        $connection->beginTransaction();
    }

    public function testGlobalColumnExists(): void
    {
        static::assertTrue($this->hasGlobalColumn($this->connection, 'custom_field_set'));
    }

    /**
     * @dataProvider tableInformationProvider
     *
     * @param DbColumn[] $expectedColumns
     */
    public function testTablesAreComplete(string $table, array $expectedColumns): void
    {
        $actualColumns = $this->fetchTableInformation($table);

        sort($actualColumns);
        sort($expectedColumns);

        static::assertEquals($expectedColumns, $actualColumns);
    }

    /**
     * @return array{0: string, 1: DbColumn[]}[]
     */
    public function tableInformationProvider(): array
    {
        return [
            [
                ProductCustomFieldSetDefinition::ENTITY_NAME,
                [
                    self::getColumn('custom_field_set_id', new BinaryType(), true),
                    self::getColumn('product_id', new BinaryType(), true),
                    self::getColumn('product_version_id', new BinaryType(), true),
                ],
            ],
        ];
    }

    /**
     * @return DbColumn
     */
    private static function getColumn(string $name, Type $type, ?bool $notNull = false): array
    {
        return [
            'name' => $name,
            'type' => $type,
            'notnull' => (bool) $notNull,
        ];
    }

    /**
     * @return DbColumn[]
     */
    private function fetchTableInformation(string $name): array
    {
        $columns = $this->connection
            ->getSchemaManager()
            ->listTableDetails($name)
            ->getColumns();

        return array_map(static fn (Column $column): array => self::getColumn(
            $column->getName(),
            $column->getType(),
            $column->getNotnull()
        ), $columns);
    }

    private function hasCustomFieldSetColumn(Connection $connection, string $table): bool
    {
        return \count(array_filter(
            $connection->getSchemaManager()->listTableColumns($table),
            static fn (Column $column): bool => $column->getName() === 'customFieldSets'
        )) > 0;
    }

    private function hasGlobalColumn(Connection $connection, string $table): bool
    {
        return \count(array_filter(
            $connection->getSchemaManager()->listTableColumns($table),
            static fn (Column $column): bool => $column->getName() === 'global'
        )) > 0;
    }
}
