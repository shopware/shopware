<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1626785125AddImportExportType;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1626785125AddImportExportType
 */
class Migration1626785125AddImportExportTypeTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $column = $this->connection->fetchOne(
            'SHOW COLUMNS FROM `import_export_profile` WHERE `Field` LIKE :column;',
            ['column' => 'type']
        );

        if ($column) {
            $this->connection->executeStatement('ALTER TABLE `import_export_profile` DROP COLUMN `type`;');
        }
    }

    public function testUpdate(): void
    {
        $migration = new Migration1626785125AddImportExportType();

        $column = $this->connection->fetchOne(
            'SHOW COLUMNS FROM `import_export_profile` WHERE `Field` LIKE :column;',
            ['column' => 'type']
        );
        static::assertFalse($column);
        $migration->update($this->connection);

        $column = $this->connection->fetchOne(
            'SHOW COLUMNS FROM `import_export_profile` WHERE `Field` LIKE :column;',
            ['column' => 'type']
        );
        static::assertSame('type', $column);
    }
}
