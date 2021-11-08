<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1636018970UnusedGuestCustomerLifetime;

class Migration1636018970UnusedGuestCustomerLifetimeTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
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

        $value = \json_decode($result['configuration_value'], true);

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
