<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1734690547SetDefaultAllowAccountLookupSetting;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1734690547SetDefaultAllowAccountLookupSetting::class)]
class Migration1734690547SetDefaultAllowAccountLookupSettingTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->delete('system_config', ['configuration_key' => 'core.loginRegistration.allowAccountLookup']);
    }

    public function testMigration(): void
    {
        static::assertEmpty($this->getConfig());

        $migration = new Migration1734690547SetDefaultAllowAccountLookupSetting();
        $migration->update($this->connection);

        $record = $this->getConfig();

        static::assertArrayHasKey('configuration_key', $record);
        static::assertArrayHasKey('configuration_value', $record);
        static::assertSame('core.loginRegistration.allowAccountLookup', $record['configuration_key']);
        static::assertSame('{"_value": true}', $record['configuration_value']);

        $migration->update($this->connection);

        $record = $this->getConfig();

        static::assertArrayHasKey('configuration_key', $record);
        static::assertArrayHasKey('configuration_value', $record);
        static::assertSame('core.loginRegistration.allowAccountLookup', $record['configuration_key']);
        static::assertSame('{"_value": true}', $record['configuration_value']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfig(): array
    {
        return $this->connection->fetchAssociative(
            'SELECT * FROM system_config WHERE configuration_key = \'core.loginRegistration.allowAccountLookup\''
        ) ?: [];
    }
}
