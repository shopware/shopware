<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1777362745MigrateAgenticCommercePluginConfig;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1777362745MigrateAgenticCommercePluginConfig::class)]
class Migration1777362745MigrateAgenticCommercePluginConfigTest extends TestCase
{
    use MigrationTestTrait;

    private const PLUGIN_PREFIX = 'SwagAgenticCommerce.';
    private const CORE_PREFIX = 'core.';
    private const OPEN_AI = 'openAiProductExport.';

    private Connection $connection;

    private Migration1777362745MigrateAgenticCommercePluginConfig $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1777362745MigrateAgenticCommercePluginConfig();
    }

    public function testMigratesFullyConfiguredOpenAiProductExportSetup(): void
    {
        $values = [
            self::OPEN_AI . 'returnPolicyUrl' => '{"_value": "https://example.com/return-policy"}',
            self::OPEN_AI . 'variantColor' => '{"_value": ["019dd43f2f5873379cf2ffb298821b9d"]}',
            self::OPEN_AI . 'variantSize' => '{"_value": ["019dd43f2f8f7350a7057d0bf2670414", "019dd43f2fc771a8a183870bae89b677"]}',
            self::OPEN_AI . 'variantSizeSystem' => '{"_value": ["019dd43f3038704abe1e1444f31129c7"]}',
            self::OPEN_AI . 'variantGender' => '{"_value": ["019dd43f2fc771a8a183870bae89b677"]}',
            self::OPEN_AI . 'variantMaterial' => '{"_value": ["019dd43f2fc771a8a183870bae89b677", "019dd43f302672c690153617b06426a4", "019dd43f306672979b0fa6bd0e39cb5d"]}',
            self::OPEN_AI . 'variantCustom' => '{"_value": []}',
        ];

        foreach ($values as $subKey => $value) {
            $this->insertConfig(self::PLUGIN_PREFIX . $subKey, $value);
        }

        $this->migration->update($this->connection);

        foreach ($values as $subKey => $expected) {
            static::assertSame(
                $expected,
                $this->fetchValue(self::CORE_PREFIX . $subKey, null),
                \sprintf('Field "%s" was not migrated verbatim.', $subKey),
            );
        }
    }

    public function testMigratesFurtherSubDomainsUnderSwagAgenticCommerce(): void
    {
        $values = [
            'experimentalDashboard.enabled' => '{"_value": true}',
            'experimentalDashboard.refreshIntervalSeconds' => '{"_value": 30}',
            'tracking.endpoint' => '{"_value": "https://tracking.example.com/v1"}',
            'tracking.allowedScopes' => '{"_value": ["read", "write"]}',
            'deeply.nested.feature.config' => '{"_value": "nested"}',
            'standaloneFlag' => '{"_value": false}',
        ];

        foreach ($values as $subKey => $value) {
            $this->insertConfig(self::PLUGIN_PREFIX . $subKey, $value);
        }

        $this->migration->update($this->connection);

        foreach ($values as $subKey => $expected) {
            static::assertSame(
                $expected,
                $this->fetchValue(self::CORE_PREFIX . $subKey, null),
                \sprintf('Unknown sub-domain key "%s%s" was unexpectedly skipped.', self::CORE_PREFIX, $subKey),
            );
        }
    }

    public function testCopiesPluginConfigToCoreNamespace(): void
    {
        $this->insertConfig(self::PLUGIN_PREFIX . self::OPEN_AI . 'returnPolicyUrl', '{"_value": "https://example.com/return"}');

        $this->migration->update($this->connection);

        static::assertSame(
            '{"_value": "https://example.com/return"}',
            $this->fetchValue(self::CORE_PREFIX . self::OPEN_AI . 'returnPolicyUrl', null),
        );
    }

    public function testCopiesPerSalesChannelScope(): void
    {
        $salesChannelId = Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL);
        $key = self::OPEN_AI . 'variantColor';

        $this->insertConfig(self::PLUGIN_PREFIX . $key, '{"_value": ["019dd43f2f5873379cf2ffb298821b9d"]}');
        $this->insertConfig(self::PLUGIN_PREFIX . $key, '{"_value": ["019dd43f2f8f7350a7057d0bf2670414"]}', $salesChannelId);

        $this->migration->update($this->connection);

        static::assertSame(
            '{"_value": ["019dd43f2f5873379cf2ffb298821b9d"]}',
            $this->fetchValue(self::CORE_PREFIX . $key, null),
        );
        static::assertSame(
            '{"_value": ["019dd43f2f8f7350a7057d0bf2670414"]}',
            $this->fetchValue(self::CORE_PREFIX . $key, $salesChannelId),
        );
    }

    public function testDoesNotOverwriteExistingCoreConfig(): void
    {
        $key = self::OPEN_AI . 'variantSize';

        $this->insertConfig(self::PLUGIN_PREFIX . $key, '{"_value": ["from-plugin"]}');
        $this->insertConfig(self::CORE_PREFIX . $key, '{"_value": ["already-set"]}');

        $this->migration->update($this->connection);

        static::assertSame(
            '{"_value": ["already-set"]}',
            $this->fetchValue(self::CORE_PREFIX . $key, null),
        );
    }

    public function testDoesNotOverwriteExistingScopedCoreConfig(): void
    {
        $salesChannelId = Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL);
        $key = self::OPEN_AI . 'variantGender';

        $this->insertConfig(self::PLUGIN_PREFIX . $key, '{"_value": ["from-plugin"]}', $salesChannelId);
        $this->insertConfig(self::CORE_PREFIX . $key, '{"_value": ["already-set"]}', $salesChannelId);

        $this->migration->update($this->connection);

        static::assertSame(
            '{"_value": ["already-set"]}',
            $this->fetchValue(self::CORE_PREFIX . $key, $salesChannelId),
        );
    }

    public function testIsIdempotent(): void
    {
        $key = self::OPEN_AI . 'variantMaterial';
        $this->insertConfig(self::PLUGIN_PREFIX . $key, '{"_value": ["019dd43f2fc771a8a183870bae89b677"]}');

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL',
            ['key' => self::CORE_PREFIX . $key],
        );

        static::assertCount(1, $rows);
        static::assertSame('{"_value": ["019dd43f2fc771a8a183870bae89b677"]}', $rows[0]['configuration_value']);
    }

    public function testIsIdempotentAcrossMixedScopes(): void
    {
        $salesChannelId = Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL);
        $key = self::OPEN_AI . 'variantSizeSystem';

        $this->insertConfig(self::PLUGIN_PREFIX . $key, '{"_value": ["019dd43f3038704abe1e1444f31129c7"]}');
        $this->insertConfig(self::PLUGIN_PREFIX . $key, '{"_value": ["019dd43f307470b6aa68b2815454bdf3"]}', $salesChannelId);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $globalRows = $this->connection->fetchAllAssociative(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL',
            ['key' => self::CORE_PREFIX . $key],
        );
        $scopedRows = $this->connection->fetchAllAssociative(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key AND sales_channel_id = :salesChannelId',
            ['key' => self::CORE_PREFIX . $key, 'salesChannelId' => $salesChannelId],
        );

        static::assertCount(1, $globalRows, 'Global scope must remain unique across multiple migration runs (catches MySQL NULL ≠ NULL pitfall).');
        static::assertCount(1, $scopedRows, 'Sales-channel scope must remain unique across multiple migration runs.');
        static::assertSame('{"_value": ["019dd43f3038704abe1e1444f31129c7"]}', $globalRows[0]['configuration_value']);
        static::assertSame('{"_value": ["019dd43f307470b6aa68b2815454bdf3"]}', $scopedRows[0]['configuration_value']);
    }

    public function testCopiesScopedRowWhenOnlyGlobalCoreExists(): void
    {
        $salesChannelId = Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL);
        $key = self::OPEN_AI . 'returnPolicyUrl';

        $this->insertConfig(self::PLUGIN_PREFIX . $key, '{"_value": "https://from-plugin.global"}');
        $this->insertConfig(self::PLUGIN_PREFIX . $key, '{"_value": "https://from-plugin.scoped"}', $salesChannelId);
        $this->insertConfig(self::CORE_PREFIX . $key, '{"_value": "https://already-set.global"}');

        $this->migration->update($this->connection);

        static::assertSame(
            '{"_value": "https://already-set.global"}',
            $this->fetchValue(self::CORE_PREFIX . $key, null),
            'Existing global core value must be preserved.',
        );
        static::assertSame(
            '{"_value": "https://from-plugin.scoped"}',
            $this->fetchValue(self::CORE_PREFIX . $key, $salesChannelId),
            'Missing scoped core value must be filled in from the plugin row.',
        );
    }

    public function testIgnoresUnrelatedPluginKeys(): void
    {
        $this->insertConfig('SomeOtherPlugin.openAiProductExport.returnPolicyUrl', '{"_value": "untouched"}');

        $this->migration->update($this->connection);

        static::assertNull(
            $this->fetchValue('core.SomeOtherPlugin.openAiProductExport.returnPolicyUrl', null),
            'Keys outside the SwagAgenticCommerce.* namespace must not be migrated.',
        );
    }

    public function testNoopWhenNoPluginRowsExist(): void
    {
        $this->migration->update($this->connection);

        $coreRows = $this->connection->fetchAllAssociative(
            'SELECT configuration_key FROM system_config WHERE configuration_key LIKE :like',
            ['like' => self::CORE_PREFIX . self::OPEN_AI . '%'],
        );

        static::assertSame([], $coreRows);
    }

    private function insertConfig(string $key, string $value, ?string $salesChannelId = null): void
    {
        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => $key,
            'configuration_value' => $value,
            'sales_channel_id' => $salesChannelId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function fetchValue(string $key, ?string $salesChannelId): ?string
    {
        $value = $this->connection->fetchOne(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key AND '
            . ($salesChannelId === null ? 'sales_channel_id IS NULL' : 'sales_channel_id = :salesChannelId'),
            $salesChannelId === null ? ['key' => $key] : ['key' => $key, 'salesChannelId' => $salesChannelId],
        );

        return $value === false ? null : (string) $value;
    }
}
