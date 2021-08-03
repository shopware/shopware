<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1626785125AddImportExportType;

class Migration1626785125AddImportExportTypeTest extends TestCase
{
    use KernelTestBehaviour;
    use BasicTestDataBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
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
