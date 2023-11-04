<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1643724178ChangePromotionCodesProfile;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1643724178ChangePromotionCodesProfile
 */
class Migration1643724178ChangePromotionCodesProfileTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $id = $this->connection->executeQuery(
            'SELECT `id` FROM `import_export_profile` WHERE `source_entity` = :source AND `system_default` = 1',
            ['source' => 'promotion_individual_code']
        )->fetchOne();
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

    /**
     * @return array<array<string, string|int>>
     */
    private function getPromotionCodesProfileMapping(): array
    {
        return json_decode((string) $this->connection->fetchOne('SELECT `mapping` FROM `import_export_profile` WHERE `source_entity` = "promotion_individual_code"'), true, 512, \JSON_THROW_ON_ERROR);
    }
}
