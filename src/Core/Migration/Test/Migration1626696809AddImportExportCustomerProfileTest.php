<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1626696809AddImportExportCustomerProfile;

class Migration1626696809AddImportExportCustomerProfileTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM `import_export_profile` WHERE `source_entity` = "customer"');
    }

    public function testMigration(): void
    {
        $migration = new Migration1626696809AddImportExportCustomerProfile();

        // Assert that the table is empty
        static::assertFalse($this->getCustomerProfileId());

        $migration->update($this->connection);

        // Assert that records have been inserted
        $id = $this->getCustomerProfileId();
        static::assertNotFalse($id);
        static::assertEquals(2, $this->getCustomerProfileTranslations($id));
    }

    private function getCustomerProfileId()
    {
        return $this->connection->fetchOne('SELECT `id` FROM `import_export_profile` WHERE `source_entity` = "customer"');
    }

    private function getCustomerProfileTranslations(string $id): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(`import_export_profile_id`) FROM `import_export_profile_translation` WHERE `import_export_profile_id` = :id',
            [':id' => $id]
        );
    }
}
