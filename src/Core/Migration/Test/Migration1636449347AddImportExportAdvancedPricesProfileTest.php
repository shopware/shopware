<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1636449347AddImportExportAdvancedPricesProfile;

class Migration1636449347AddImportExportAdvancedPricesProfileTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM `import_export_profile` WHERE `source_entity` = "product_price"');
    }

    public function testMigration(): void
    {
        $migration = new Migration1636449347AddImportExportAdvancedPricesProfile();

        // Assert that the table is empty
        static::assertFalse($this->getAdvancedPricesProfileId());

        $migration->update($this->connection);

        // Assert that records have been inserted
        $id = $this->getAdvancedPricesProfileId();
        static::assertNotFalse($id);
        static::assertEquals(2, $this->getAdvancedPricesProfileTranslations($id));
    }

    private function getAdvancedPricesProfileId()
    {
        return $this->connection->fetchOne('SELECT `id` FROM `import_export_profile` WHERE `source_entity` = "product_price"');
    }

    private function getAdvancedPricesProfileTranslations(string $id): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(`import_export_profile_id`) FROM `import_export_profile_translation` WHERE `import_export_profile_id` = :id',
            [':id' => $id]
        );
    }
}
