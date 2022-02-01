<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1643724178ChangePromotionCodesProfile;

class Migration1643724178ChangePromotionCodesProfileTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $id = $this->connection->executeQuery(
            'SELECT `id` FROM `import_export_profile` WHERE `source_entity` = :source AND `system_default` = 1',
            ['source' => 'promotion_individual_code']
        )->fetchColumn();
        $this->connection->update('import_export_profile', ['mapping' => json_encode([])], ['id' => $id]);
    }

    public function testMigration(): void
    {
        $migration = new Migration1643724178ChangePromotionCodesProfile();

        // Assert that the table exists
        static::assertNotNull($this->getPromotionCodesProfileMapping());

        $migration->update($this->connection);

        // Assert that records have been changed
        $mapping = $this->getPromotionCodesProfileMapping();
        static::assertNotEmpty($mapping);
        static::assertEquals([
            ['key' => 'id', 'mappedKey' => 'id', 'position' => 0],
            ['key' => 'promotionId', 'mappedKey' => 'promotion_id', 'position' => 1],
            ['key' => 'code', 'mappedKey' => 'code', 'position' => 2],
        ], $mapping);
    }

    private function getPromotionCodesProfileMapping()
    {
        return json_decode($this->connection->fetchOne('SELECT `mapping` FROM `import_export_profile` WHERE `source_entity` = "promotion_individual_code"'), true);
    }
}
