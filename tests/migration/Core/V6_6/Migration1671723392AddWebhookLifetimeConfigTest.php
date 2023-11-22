<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1671723392AddWebhookLifetimeConfig;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1671723392AddWebhookLifetimeConfig::class)]
class Migration1671723392AddWebhookLifetimeConfigTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->delete('system_config', ['configuration_key' => 'core.webhook.entryLifetimeSeconds']);
    }

    public function testMigration(): void
    {
        static::assertEmpty($this->getConfig());

        $migration = new Migration1671723392AddWebhookLifetimeConfig();
        $migration->update($this->connection);

        $record = $this->getConfig();

        static::assertArrayHasKey('configuration_key', $record);
        static::assertArrayHasKey('configuration_value', $record);
        static::assertSame('core.webhook.entryLifetimeSeconds', $record['configuration_key']);
        static::assertSame('{"_value": "1209600"}', $record['configuration_value']);

        $migration = new Migration1671723392AddWebhookLifetimeConfig();
        $migration->update($this->connection);

        $record = $this->getConfig();

        static::assertArrayHasKey('configuration_key', $record);
        static::assertArrayHasKey('configuration_value', $record);
        static::assertSame('core.webhook.entryLifetimeSeconds', $record['configuration_key']);
        static::assertSame('{"_value": "1209600"}', $record['configuration_value']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfig(): array
    {
        return $this->connection->fetchAssociative(
            'SELECT * FROM system_config WHERE configuration_key = \'core.webhook.entryLifetimeSeconds\''
        ) ?: [];
    }
}
