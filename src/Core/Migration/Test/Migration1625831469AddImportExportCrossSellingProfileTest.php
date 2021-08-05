<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1625831469AddImportExportCrossSellingProfile;

class Migration1625831469AddImportExportCrossSellingProfileTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM `import_export_profile` WHERE `source_entity` = "product_cross_selling"');
    }

    public function testMigration(): void
    {
        $migration = new Migration1625831469AddImportExportCrossSellingProfile();

        // Assert that the table is empty
        static::assertFalse($this->getCrossSellingProfileId());

        $migration->update($this->connection);

        // Assert that records have been inserted
        $id = $this->getCrossSellingProfileId();
        static::assertNotFalse($id);
        static::assertEquals(2, $this->getCrossSellingProfileTranslations($id));
    }

    private function getCrossSellingProfileId()
    {
        return $this->connection->fetchOne('SELECT `id` FROM `import_export_profile` WHERE `source_entity` = "product_cross_selling"');
    }

    private function getCrossSellingProfileTranslations(string $id): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(`import_export_profile_id`) FROM `import_export_profile_translation` WHERE `import_export_profile_id` = :id',
            [':id' => $id]
        );
    }
}
