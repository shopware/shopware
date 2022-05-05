<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1649315608AllowDisable;

/**
 * @internal
 */
class Migration1649315608AllowDisableTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->prepare();
    }

    public function testMigration(): void
    {
        $migration = new Migration1649315608AllowDisable();
        $resultColumnExists = $this->hasColumn('app', 'allow_disable');
        static::assertFalse($resultColumnExists);

        $migration->update($this->connection);
        $migration->update($this->connection);

        $resultColumnExists = $this->hasColumn('app', 'allow_disable');
        static::assertTrue($resultColumnExists);
    }

    private function prepare(): void
    {
        $resultColumnExists = $this->hasColumn('app', 'allow_disable');

        if ($resultColumnExists) {
            $this->connection->executeStatement('ALTER TABLE `app` DROP COLUMN `allow_disable`');
        }
    }

    private function hasColumn(string $table, string $columnName): bool
    {
        return \count(array_filter(
            $this->connection->getSchemaManager()->listTableColumns($table),
            static function (Column $column) use ($columnName): bool {
                return $column->getName() === $columnName;
            }
        )) > 0;
    }
}
