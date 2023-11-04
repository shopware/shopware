<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1627983652AddOrderExportProfile;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1627983652AddOrderExportProfile
 */
class Migration1627983652AddOrderExportProfileTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->executeStatement('DELETE FROM `import_export_profile` WHERE `source_entity` = "order"');
    }

    public function testMigrationCreatedSuccessfully(): void
    {
        static::assertFalse((bool) $this->getProfileId());

        $migration = new Migration1627983652AddOrderExportProfile();
        $migration->update($this->connection);

        $id = $this->getProfileId();

        static::assertNotFalse($id);
        static::assertEquals(2, $this->getAmountOfProfileTranslations($id));
    }

    /**
     * @return false|mixed
     */
    private function getProfileId()
    {
        return $this->connection->fetchOne('SELECT * FROM `import_export_profile` WHERE `source_entity` = "order"');
    }

    private function getAmountOfProfileTranslations(string $id): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(`import_export_profile_id`) FROM `import_export_profile_translation` WHERE `import_export_profile_id` = :id',
            ['id' => $id]
        );
    }
}
