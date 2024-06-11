<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1717572627RemoveImportExportProfileName;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(Migration1717572627RemoveImportExportProfileName::class)]
class Migration1717572627RemoveImportExportProfileNameTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdateDestructiveRemovesColumn(): void
    {
        $exists = $this->columnExists();

        if (!$exists) {
            $this->addColumn();
        }

        $migration = new Migration1717572627RemoveImportExportProfileName();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse($this->columnExists());

        if ($exists) {
            $this->addColumn();
        }
    }

    private function addColumn(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE `import_export_profile` ADD COLUMN `name` VARCHAR(255) DEFAULT NULL'
        );
    }

    private function columnExists(): bool
    {
        $exists = $this->connection->fetchOne(
            'SHOW COLUMNS FROM `import_export_profile` WHERE `Field` LIKE "name"',
        );

        return !empty($exists);
    }
}
