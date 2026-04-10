<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1770621743CopyUsageDataConsentState;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1770621743CopyUsageDataConsentState::class)]
class Migration1770621743CopyUsageDataConsentStateTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement(
            'DELETE FROM consent_state WHERE name = :name AND identifier = :identifier',
            ['name' => 'backend_data', 'identifier' => 'system']
        );

        $this->connection->executeStatement(
            'DELETE FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL',
            ['key' => 'core.usageData.consentState']
        );
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1770621743CopyUsageDataConsentState();

        static::assertSame(1770621743, $migration->getCreationTimestamp());
    }

    public function testMigrationCopiesAcceptedConsentState(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at)
             VALUES (UNHEX(REPLACE(UUID(), "-", "")), :key, :value, NULL, NOW(3))',
            [
                'key' => 'core.usageData.consentState',
                'value' => json_encode(['_value' => 'accepted'], \JSON_THROW_ON_ERROR),
            ]
        );

        $migration = new Migration1770621743CopyUsageDataConsentState();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $row = $this->connection->fetchAssociative(
            'SELECT name, identifier, state, actor, updated_at
             FROM consent_state
             WHERE name = :name AND identifier = :identifier',
            ['name' => 'backend_data', 'identifier' => 'system']
        );

        static::assertIsArray($row);
        static::assertSame('backend_data', $row['name']);
        static::assertSame('system', $row['identifier']);
        static::assertSame('accepted', $row['state']);
        static::assertSame('migration', $row['actor']);
        static::assertNotNull($row['updated_at']);
    }

    public function testMigrationSkipsNonAcceptedValues(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at)
             VALUES (UNHEX(REPLACE(UUID(), "-", "")), :key, :value, NULL, NOW(3))',
            [
                'key' => 'core.usageData.consentState',
                'value' => json_encode(['_value' => 'requested'], \JSON_THROW_ON_ERROR),
            ]
        );

        $migration = new Migration1770621743CopyUsageDataConsentState();
        $migration->update($this->connection);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM consent_state WHERE name = :name AND identifier = :identifier',
            ['name' => 'backend_data', 'identifier' => 'system']
        );

        static::assertSame('0', (string) $count);
    }
}
