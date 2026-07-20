<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1755497870RemoveLabelTranslationOfImportExportProfile;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(Migration1755497870RemoveLabelTranslationOfImportExportProfile::class)]
class Migration1755497870RemoveLabelTranslationOfImportExportProfileTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1755497870, (new Migration1755497870RemoveLabelTranslationOfImportExportProfile())->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        if (!TableHelper::tableExists($this->connection, 'import_export_profile_translation')) {
            $this->addTable();
        }

        static::assertTrue(TableHelper::tableExists($this->connection, 'import_export_profile_translation'));

        $migration = new Migration1755497870RemoveLabelTranslationOfImportExportProfile();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::tableExists($this->connection, 'import_export_profile_translation'));
    }

    private function addTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE `import_export_profile_translation` (
                `id` BINARY(16) NOT NULL,
                `label` VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }
}
