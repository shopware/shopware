<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1778050000MigrateServicesConsentToNativeConsent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1778050000MigrateServicesConsentToNativeConsent::class)]
class Migration1778050000MigrateServicesConsentToNativeConsentTest extends TestCase
{
    private const LEGACY_CONFIG_KEY = 'core.services.permissionsConsent';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement(
            'DELETE FROM consent_state WHERE name = :name AND identifier = :identifier',
            ['name' => 'service_consent', 'identifier' => 'system']
        );

        $this->connection->executeStatement(
            'DELETE FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL',
            ['key' => self::LEGACY_CONFIG_KEY]
        );
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1778050000MigrateServicesConsentToNativeConsent();

        static::assertSame(1778050000, $migration->getCreationTimestamp());
    }

    public function testMigrationBackfillsAcceptedConsentAndRemovesLegacyConfig(): void
    {
        $this->insertLegacyConfig([
            '_value' => [
                'identifier' => 'legacy-id',
                'revision' => '2026-05-05',
                'consentingUserId' => 'user-id',
                'grantedAt' => '2026-05-05T12:13:14.123+00:00',
            ],
        ]);

        $migration = new Migration1778050000MigrateServicesConsentToNativeConsent();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $row = $this->connection->fetchAssociative(
            'SELECT name, identifier, state, actor, revision FROM consent_state WHERE name = :name AND identifier = :identifier',
            ['name' => 'service_consent', 'identifier' => 'system']
        );

        static::assertIsArray($row);
        static::assertSame('service_consent', $row['name']);
        static::assertSame('system', $row['identifier']);
        static::assertSame('accepted', $row['state']);
        static::assertSame('user-id', $row['actor']);
        static::assertSame('2026-05-05', $row['revision']);

        $legacyCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL',
            ['key' => self::LEGACY_CONFIG_KEY]
        );

        static::assertSame('0', (string) $legacyCount);
    }

    public function testExistingNativeConsentWins(): void
    {
        $this->connection->executeStatement(
            'INSERT INTO consent_state (id, name, identifier, state, actor, revision, updated_at)
             VALUES (UNHEX(REPLACE(UUID(), "-", "")), :name, :identifier, :state, :actor, :revision, NOW(3))',
            [
                'name' => 'service_consent',
                'identifier' => 'system',
                'state' => 'accepted',
                'actor' => 'native-user',
                'revision' => '2026-06-06',
            ]
        );

        $this->insertLegacyConfig([
            '_value' => [
                'identifier' => 'legacy-id',
                'revision' => '2026-05-05',
                'consentingUserId' => 'legacy-user',
                'grantedAt' => '2026-05-05T12:13:14.123+00:00',
            ],
        ]);

        $migration = new Migration1778050000MigrateServicesConsentToNativeConsent();
        $migration->update($this->connection);

        $row = $this->connection->fetchAssociative(
            'SELECT actor, revision FROM consent_state WHERE name = :name AND identifier = :identifier',
            ['name' => 'service_consent', 'identifier' => 'system']
        );

        static::assertIsArray($row);
        static::assertSame('native-user', $row['actor']);
        static::assertSame('2026-06-06', $row['revision']);
    }

    public function testMalformedLegacyConfigIsIgnoredAndRemoved(): void
    {
        $this->insertLegacyConfig([
            '_value' => [
                'identifier' => 'legacy-id',
                'revision' => '2026-05-05',
                'consentingUserId' => 'legacy-user',
                'grantedAt' => 'not-a-date',
            ],
        ]);

        $migration = new Migration1778050000MigrateServicesConsentToNativeConsent();
        $migration->update($this->connection);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM consent_state WHERE name = :name AND identifier = :identifier',
            ['name' => 'service_consent', 'identifier' => 'system']
        );

        static::assertSame('0', (string) $count);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertLegacyConfig(array $payload): void
    {
        $this->connection->executeStatement(
            'INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at)
             VALUES (UNHEX(REPLACE(UUID(), "-", "")), :key, :value, NULL, NOW(3))',
            [
                'key' => self::LEGACY_CONFIG_KEY,
                'value' => json_encode($payload, \JSON_THROW_ON_ERROR),
            ]
        );
    }
}
