<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1626696809AddImportExportCustomerProfile;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1626696809AddImportExportCustomerProfile
 */
class Migration1626696809AddImportExportCustomerProfileTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DELETE FROM `import_export_profile` WHERE `source_entity` = "customer"');
    }

    public function testMigration(): void
    {
        $migration = new Migration1626696809AddImportExportCustomerProfile();

        // Assert that the table is empty
        $id = $this->getCustomerProfileId();
        static::assertFalse($id);

        $migration->update($this->connection);

        // Assert that records have been inserted
        $id = $this->getCustomerProfileId();
        static::assertNotFalse($id);
        static::assertEquals(2, $this->getCustomerProfileTranslations($id));
    }

    /**
     * @return mixed|false
     */
    private function getCustomerProfileId()
    {
        return $this->connection->fetchOne('SELECT `id` FROM `import_export_profile` WHERE `source_entity` = "customer"');
    }

    private function getCustomerProfileTranslations(string $id): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(`import_export_profile_id`) FROM `import_export_profile_translation` WHERE `import_export_profile_id` = :id',
            ['id' => $id]
        );
    }
}
