<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1636018970UnusedGuestCustomerLifetime;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1636018970UnusedGuestCustomerLifetime
 */
class Migration1636018970UnusedGuestCustomerLifetimeTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->rollback();
    }

    public function testMigration(): void
    {
        $result = $this->connection->executeQuery('
            SELECT configuration_key, configuration_value
            FROM `system_config`
            WHERE configuration_key = "core.loginRegistration.unusedGuestCustomerLifetime"
        ')->fetchOne();

        static::assertFalse($result);

        $this->migrate();

        $result = $this->connection->executeQuery('
            SELECT configuration_key, configuration_value
            FROM `system_config`
            WHERE configuration_key = "core.loginRegistration.unusedGuestCustomerLifetime"
        ')->fetchAssociative();

        static::assertIsArray($result);
        static::assertEquals('core.loginRegistration.unusedGuestCustomerLifetime', $result['configuration_key']);

        $value = \json_decode((string) $result['configuration_value'], true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(86400, $value['_value']);
    }

    private function migrate(): void
    {
        (new Migration1636018970UnusedGuestCustomerLifetime())->update($this->connection);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('
            DELETE
            FROM `system_config`
            WHERE configuration_key = "core.loginRegistration.unusedGuestCustomerLifetime"
        ');
    }
}
