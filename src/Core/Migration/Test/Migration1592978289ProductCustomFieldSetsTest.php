<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCustomFieldSet\ProductCustomFieldSetDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\Migration1592978289ProductCustomFieldSets;

class Migration1592978289ProductCustomFieldSetsTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
    }

    /**
     * @before
     */
    public function initialise(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $migration = new Migration1592978289ProductCustomFieldSets();

        $connection->rollBack();

        if ($this->hasCustomFieldSetColumn($connection, 'product')) {
            $connection->executeUpdate('ALTER TABLE `product` DROP COLUMN `customFieldSets`;');
        }

        if ($this->hasGlobalColumn($connection, 'custom_field_set')) {
            $connection->executeUpdate('ALTER TABLE `custom_field_set` DROP COLUMN `global`;');
        }

        $connection->executeUpdate('DROP TABLE IF EXISTS `product_custom_field_set`;');

        $migration->update($connection);

        $connection->beginTransaction();
    }

    public function testGlobalColumnExists(): void
    {
        static::assertTrue($this->hasGlobalColumn($this->connection, 'custom_field_set'));
    }

    /**
     * @dataProvider tableInformationProvider
     */
    public function testTablesAreComplete(string $table, array $expectedColumns): void
    {
        $actualColumns = $this->fetchTableInformation($table);

        sort($actualColumns);
        sort($expectedColumns);

        static::assertEquals($expectedColumns, $actualColumns);
    }

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

    private static function getColumn(string $name, Type $type, ?bool $notNull = false): array
    {
        return [
            'name' => $name,
            'type' => $type,
            'notnull' => $notNull,
        ];
    }

    private function fetchTableInformation(string $name): array
    {
        $columns = $this->connection
            ->getSchemaManager()
            ->listTableDetails($name)
            ->getColumns();

        return array_map(static function (Column $column): array {
            return self::getColumn(
                $column->getName(),
                $column->getType(),
                $column->getNotnull()
            );
        }, $columns);
    }

    private function hasCustomFieldSetColumn(Connection $connection, string $table): bool
    {
        return \count(array_filter(
            $connection->getSchemaManager()->listTableColumns($table),
            static function (Column $column): bool {
                return $column->getName() === 'customFieldSets';
            }
        )) > 0;
    }

    private function hasGlobalColumn(Connection $connection, string $table): bool
    {
        return \count(array_filter(
            $connection->getSchemaManager()->listTableColumns($table),
            static function (Column $column): bool {
                return $column->getName() === 'global';
            }
        )) > 0;
    }
}
