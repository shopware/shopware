<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
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

    public function testUpdate(): void
    {
        if (!$this->tableExists()) {
            $this->addTable();
        }

        static::assertTrue($this->tableExists());

        $migration = new Migration1755497870RemoveLabelTranslationOfImportExportProfile();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse($this->tableExists());
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

    private function tableExists(): bool
    {
        $exists = $this->connection->fetchOne(
            'SHOW TABLES LIKE "import_export_profile_translation"'
        );

        return !empty($exists);
    }
}
