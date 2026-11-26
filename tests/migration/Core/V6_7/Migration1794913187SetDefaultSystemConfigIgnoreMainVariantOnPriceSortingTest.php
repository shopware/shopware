<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1794913187SetDefaultSystemConfigIgnoreMainVariantOnPriceSorting;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1794913187SetDefaultSystemConfigIgnoreMainVariantOnPriceSorting::class)]
class Migration1794913187SetDefaultSystemConfigIgnoreMainVariantOnPriceSortingTest extends TestCase
{
    use MigrationTestTrait;
    private const CONFIG_KEY = 'core.listing.ignoreMainVariantOnPriceSorting';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->delete('system_config', ['configuration_key' => self::CONFIG_KEY]);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1794913187, (new Migration1794913187SetDefaultSystemConfigIgnoreMainVariantOnPriceSorting())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        static::assertCount(0, $this->getConfig());

        $migration = new Migration1794913187SetDefaultSystemConfigIgnoreMainVariantOnPriceSorting();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $record = $this->getConfig();

        static::assertArrayHasKey('configuration_key', $record);
        static::assertArrayHasKey('configuration_value', $record);
        static::assertSame(self::CONFIG_KEY, $record['configuration_key']);
        static::assertSame('{"_value": false}', $record['configuration_value']);

        $value = \sprintf('{"_value": "%s"}', Uuid::randomHex());
        $this->connection->update('system_config', ['configuration_value' => $value], ['configuration_key' => self::CONFIG_KEY]);

        $migration->update($this->connection);
        $migration->update($this->connection);

        $record = $this->getConfig();

        static::assertArrayHasKey('configuration_key', $record);
        static::assertArrayHasKey('configuration_value', $record);
        static::assertSame(self::CONFIG_KEY, $record['configuration_key']);
        static::assertSame($value, $record['configuration_value']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfig(): array
    {
        return $this->connection->fetchAssociative('SELECT * FROM system_config WHERE configuration_key = :key', [
            'key' => self::CONFIG_KEY,
        ]) ?: [];
    }
}
