<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1708685281ChangeAppPathColumnToLongerVarchar;

/**
 * @internal
 */
#[CoversClass(Migration1708685281ChangeAppPathColumnToLongerVarchar::class)]
class Migration1708685281ChangeAppPathColumnToLongerVarcharTest extends TestCase
{
    private Connection $connection;

    private Migration1708685281ChangeAppPathColumnToLongerVarchar $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1708685281ChangeAppPathColumnToLongerVarchar();
    }

    public function testUpdate(): void
    {
        if ($this->isPathColumnLongVarchar()) {
            $this->setDataTypeToDefaultVarchar();
        }

        static::assertFalse($this->isPathColumnLongVarchar());

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        static::assertTrue($this->isPathColumnLongVarchar());
    }

    private function isPathColumnLongVarchar(): bool
    {
        $sql = 'SHOW COLUMNS FROM `app` WHERE `field` = \'path\';';
        $result = $this->connection->executeQuery($sql)->fetchAssociative();

        static::assertIsArray($result);

        return $result['Type'] === 'varchar(4096)';
    }

    private function setDataTypeToDefaultVarchar(): void
    {
        $sql = 'ALTER TABLE `app` MODIFY COLUMN `path` VARCHAR(255);';

        $this->connection->executeQuery($sql);
    }
}
