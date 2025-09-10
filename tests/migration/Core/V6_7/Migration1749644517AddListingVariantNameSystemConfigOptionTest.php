<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1749644517AddListingVariantNameSystemConfigOption;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1749644517AddListingVariantNameSystemConfigOption::class)]
class Migration1749644517AddListingVariantNameSystemConfigOptionTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->delete('system_config', ['configuration_key' => 'core.listing.showVariantOptionInSearchSuggestionResult']);
    }

    public function testMigration(): void
    {
        static::assertEmpty($this->getConfig());

        $migration = new Migration1749644517AddListingVariantNameSystemConfigOption();
        $migration->update($this->connection);

        $record = $this->getConfig();

        static::assertArrayHasKey('configuration_key', $record);
        static::assertArrayHasKey('configuration_value', $record);
        static::assertSame('core.listing.showVariantOptionInSearchSuggestionResult', $record['configuration_key']);

        $value = \sprintf('{"_value": "%s"}', Uuid::randomHex());
        $this->connection->update('system_config', ['configuration_value' => $value], ['configuration_key' => 'core.listing.showVariantOptionInSearchSuggestionResult']);

        $migration->update($this->connection);

        $record = $this->getConfig();

        static::assertArrayHasKey('configuration_key', $record);
        static::assertArrayHasKey('configuration_value', $record);
        static::assertSame('core.listing.showVariantOptionInSearchSuggestionResult', $record['configuration_key']);
        static::assertSame($value, $record['configuration_value']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfig(): array
    {
        return $this->connection->fetchAssociative(
            'SELECT * FROM system_config WHERE configuration_key = \'core.listing.showVariantOptionInSearchSuggestionResult\''
        ) ?: [];
    }
}
