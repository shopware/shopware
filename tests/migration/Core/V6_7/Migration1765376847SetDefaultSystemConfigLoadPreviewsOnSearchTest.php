<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1765376847SetDefaultSystemConfigLoadPreviewsOnSearch;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1765376847SetDefaultSystemConfigLoadPreviewsOnSearch::class)]
class Migration1765376847SetDefaultSystemConfigLoadPreviewsOnSearchTest extends TestCase
{
    use MigrationTestTrait;
    private const CONFIG_KEY = 'core.listing.findBestVariant';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->delete('system_config', ['configuration_key' => self::CONFIG_KEY]);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1765376847, (new Migration1765376847SetDefaultSystemConfigLoadPreviewsOnSearch())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        static::assertEmpty($this->getConfig());

        $migration = new Migration1765376847SetDefaultSystemConfigLoadPreviewsOnSearch();
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
