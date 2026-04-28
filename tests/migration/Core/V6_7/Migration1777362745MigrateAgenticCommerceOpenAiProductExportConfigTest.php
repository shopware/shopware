<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1777362745MigrateAgenticCommerceOpenAiProductExportConfig;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1777362745MigrateAgenticCommerceOpenAiProductExportConfig::class)]
class Migration1777362745MigrateAgenticCommerceOpenAiProductExportConfigTest extends TestCase
{
    use MigrationTestTrait;

    private const PLUGIN_PREFIX = 'SwagAgenticCommerce.openAiProductExport.';
    private const CORE_PREFIX = 'core.openAiProductExport.';

    private Connection $connection;

    private Migration1777362745MigrateAgenticCommerceOpenAiProductExportConfig $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1777362745MigrateAgenticCommerceOpenAiProductExportConfig();
    }

    public function testMigratesFullyConfiguredPluginSetup(): void
    {
        $values = [
            'returnPolicyUrl' => '{"_value": "https://example.com/return-policy"}',
            'variantColor' => '{"_value": ["019dd43f2f5873379cf2ffb298821b9d"]}',
            'variantSize' => '{"_value": ["019dd43f2f8f7350a7057d0bf2670414", "019dd43f2fc771a8a183870bae89b677"]}',
            'variantSizeSystem' => '{"_value": ["019dd43f3038704abe1e1444f31129c7"]}',
            'variantGender' => '{"_value": ["019dd43f2fc771a8a183870bae89b677"]}',
            'variantMaterial' => '{"_value": ["019dd43f2fc771a8a183870bae89b677", "019dd43f302672c690153617b06426a4", "019dd43f306672979b0fa6bd0e39cb5d"]}',
            'variantCustom' => '{"_value": []}',
        ];

        foreach ($values as $field => $value) {
            $this->insertConfig(self::PLUGIN_PREFIX . $field, $value);
        }

        $this->migration->update($this->connection);

        foreach ($values as $field => $expected) {
            static::assertSame(
                $expected,
                $this->fetchValue(self::CORE_PREFIX . $field, null),
                \sprintf('Field "%s" was not migrated verbatim.', $field),
            );
        }

        $coreRows = $this->connection->fetchAllAssociative(
            'SELECT configuration_key FROM system_config WHERE configuration_key LIKE :like',
            ['like' => self::CORE_PREFIX . '%'],
        );
        static::assertCount(\count($values), $coreRows, 'All seven plugin fields must result in exactly seven core rows.');
    }

    public function testCopiesPluginConfigToCoreNamespace(): void
    {
        $this->insertConfig(self::PLUGIN_PREFIX . 'returnPolicyUrl', '{"_value": "https://example.com/return"}');
        $this->insertConfig(self::PLUGIN_PREFIX . 'variantColor', '{"_value": ["color"]}');

        $this->migration->update($this->connection);

        static::assertSame(
            '{"_value": "https://example.com/return"}',
            $this->fetchValue(self::CORE_PREFIX . 'returnPolicyUrl', null),
        );
        static::assertSame(
            '{"_value": ["color"]}',
            $this->fetchValue(self::CORE_PREFIX . 'variantColor', null),
        );
    }

    public function testCopiesPerSalesChannelScope(): void
    {
        $salesChannelId = Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL);

        $this->insertConfig(self::PLUGIN_PREFIX . 'returnPolicyUrl', '{"_value": "https://global.example"}');
        $this->insertConfig(self::PLUGIN_PREFIX . 'returnPolicyUrl', '{"_value": "https://channel.example"}', $salesChannelId);

        $this->migration->update($this->connection);

        static::assertSame(
            '{"_value": "https://global.example"}',
            $this->fetchValue(self::CORE_PREFIX . 'returnPolicyUrl', null),
        );
        static::assertSame(
            '{"_value": "https://channel.example"}',
            $this->fetchValue(self::CORE_PREFIX . 'returnPolicyUrl', $salesChannelId),
        );
    }

    public function testDoesNotOverwriteExistingCoreConfig(): void
    {
        $this->insertConfig(self::PLUGIN_PREFIX . 'returnPolicyUrl', '{"_value": "https://from-plugin.example"}');
        $this->insertConfig(self::CORE_PREFIX . 'returnPolicyUrl', '{"_value": "https://already-set.example"}');

        $this->migration->update($this->connection);

        static::assertSame(
            '{"_value": "https://already-set.example"}',
            $this->fetchValue(self::CORE_PREFIX . 'returnPolicyUrl', null),
        );
    }

    public function testIsIdempotent(): void
    {
        $this->insertConfig(self::PLUGIN_PREFIX . 'returnPolicyUrl', '{"_value": "https://example.com/return"}');

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL',
            ['key' => self::CORE_PREFIX . 'returnPolicyUrl'],
        );

        static::assertCount(1, $rows);
        static::assertSame('{"_value": "https://example.com/return"}', $rows[0]['configuration_value']);
    }

    public function testIgnoresUnrelatedPluginKeys(): void
    {
        $this->insertConfig('SwagAgenticCommerce.somethingElse.foo', '{"_value": "bar"}');

        $this->migration->update($this->connection);

        $coreRows = $this->connection->fetchAllAssociative(
            'SELECT configuration_key FROM system_config WHERE configuration_key LIKE :like',
            ['like' => 'core.somethingElse.%'],
        );

        static::assertSame([], $coreRows);
    }

    public function testNoopWhenNoPluginRowsExist(): void
    {
        $this->migration->update($this->connection);

        $coreRows = $this->connection->fetchAllAssociative(
            'SELECT configuration_key FROM system_config WHERE configuration_key LIKE :like',
            ['like' => self::CORE_PREFIX . '%'],
        );

        static::assertSame([], $coreRows);
    }

    public function testDoesNotOverwriteExistingScopedCoreConfig(): void
    {
        $salesChannelId = Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL);

        $this->insertConfig(self::PLUGIN_PREFIX . 'returnPolicyUrl', '{"_value": "https://from-plugin.example"}', $salesChannelId);
        $this->insertConfig(self::CORE_PREFIX . 'returnPolicyUrl', '{"_value": "https://already-set.example"}', $salesChannelId);

        $this->migration->update($this->connection);

        static::assertSame(
            '{"_value": "https://already-set.example"}',
            $this->fetchValue(self::CORE_PREFIX . 'returnPolicyUrl', $salesChannelId),
        );
    }

    public function testIsIdempotentAcrossMixedScopes(): void
    {
        $salesChannelId = Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL);

        $this->insertConfig(self::PLUGIN_PREFIX . 'returnPolicyUrl', '{"_value": "https://global.example"}');
        $this->insertConfig(self::PLUGIN_PREFIX . 'returnPolicyUrl', '{"_value": "https://channel.example"}', $salesChannelId);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $globalRows = $this->connection->fetchAllAssociative(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL',
            ['key' => self::CORE_PREFIX . 'returnPolicyUrl'],
        );
        $scopedRows = $this->connection->fetchAllAssociative(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key AND sales_channel_id = :salesChannelId',
            ['key' => self::CORE_PREFIX . 'returnPolicyUrl', 'salesChannelId' => $salesChannelId],
        );

        static::assertCount(1, $globalRows, 'Global scope must remain unique across multiple migration runs (catches MySQL NULL ≠ NULL pitfall).');
        static::assertCount(1, $scopedRows, 'Sales-channel scope must remain unique across multiple migration runs.');
        static::assertSame('{"_value": "https://global.example"}', $globalRows[0]['configuration_value']);
        static::assertSame('{"_value": "https://channel.example"}', $scopedRows[0]['configuration_value']);
    }

    public function testCopiesScopedRowWhenOnlyGlobalCoreExists(): void
    {
        $salesChannelId = Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL);

        $this->insertConfig(self::PLUGIN_PREFIX . 'returnPolicyUrl', '{"_value": "https://from-plugin.global"}');
        $this->insertConfig(self::PLUGIN_PREFIX . 'returnPolicyUrl', '{"_value": "https://from-plugin.scoped"}', $salesChannelId);
        $this->insertConfig(self::CORE_PREFIX . 'returnPolicyUrl', '{"_value": "https://already-set.global"}');

        $this->migration->update($this->connection);

        static::assertSame(
            '{"_value": "https://already-set.global"}',
            $this->fetchValue(self::CORE_PREFIX . 'returnPolicyUrl', null),
            'Existing global core value must be preserved.',
        );
        static::assertSame(
            '{"_value": "https://from-plugin.scoped"}',
            $this->fetchValue(self::CORE_PREFIX . 'returnPolicyUrl', $salesChannelId),
            'Missing scoped core value must be filled in from the plugin row.',
        );
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
