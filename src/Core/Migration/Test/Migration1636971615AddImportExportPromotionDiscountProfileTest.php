<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1636971615AddImportExportPromotionDiscountProfile;

class Migration1636971615AddImportExportPromotionDiscountProfileTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM `import_export_profile` WHERE `source_entity` = "promotion_discount"');
    }

    public function testMigration(): void
    {
        $migration = new Migration1636971615AddImportExportPromotionDiscountProfile();

        // Assert that the table is empty
        static::assertFalse($this->getPromotionDiscountsProfileId());

        $migration->update($this->connection);

        // Assert that records have been inserted
        $id = $this->getPromotionDiscountsProfileId();
        static::assertNotFalse($id);
        static::assertEquals(2, $this->getPromotionDiscountsProfileTranslations($id));
    }

    private function getPromotionDiscountsProfileId()
    {
        return $this->connection->fetchOne('SELECT `id` FROM `import_export_profile` WHERE `source_entity` = "promotion_discount"');
    }

    private function getPromotionDiscountsProfileTranslations(string $id): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(`import_export_profile_id`) FROM `import_export_profile_translation` WHERE `import_export_profile_id` = :id',
            [':id' => $id]
        );
    }
}
