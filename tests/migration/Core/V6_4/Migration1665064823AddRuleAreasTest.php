<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1665064823AddRuleAreas;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1665064823AddRuleAreas
 */
class Migration1665064823AddRuleAreasTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMigrationColumn(): void
    {
        $this->removeColumn();
        static::assertFalse($this->hasColumn('rule', 'areas'));

        $migration = new Migration1665064823AddRuleAreas();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->hasColumn('rule', 'areas'));
    }

    private function removeColumn(): void
    {
        if ($this->hasColumn('rule', 'areas')) {
            $this->connection->executeStatement('ALTER TABLE `rule` DROP COLUMN `areas`');
        }
    }

    private function hasColumn(string $table, string $columnName): bool
    {
        return \in_array($columnName, array_column($this->connection->fetchAllAssociative(\sprintf('SHOW COLUMNS FROM `%s`', $table)), 'Field'), true);
    }
}
