<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1742484083TransitionToAddressInputFieldArrangement;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1742484083TransitionToAddressInputFieldArrangement::class)]
class Migration1742484083TransitionToAddressInputFieldArrangementTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $this->revertMigration();
        $this->prepareSystemConfig();

        $migration = new Migration1742484083TransitionToAddressInputFieldArrangement();
        $migration->update($this->connection);

        $this->assertNewConfiguration();

        $migration->update($this->connection);

        $this->assertNewConfiguration();
    }

    public function testMigrateDestructive(): void
    {
        $this->revertMigration();
        $this->prepareSystemConfig();

        $migration = new Migration1742484083TransitionToAddressInputFieldArrangement();
        $migration->update($this->connection);
        $migration->updateDestructive($this->connection);

        $this->assertNewConfiguration();

        $oldConfiguration = $this->connection->fetchAllAssociativeIndexed(
            'SELECT sales_channel_id, configuration_value FROM system_config WHERE configuration_key = ?',
            [Migration1742484083TransitionToAddressInputFieldArrangement::OLD_CONFIG_KEY]
        );

        static::assertSame([], $oldConfiguration);
    }

    public function testNotOverrideExistingConfig(): void
    {
        $this->revertMigration();
        $this->prepareSystemConfig();

        $migration = new Migration1742484083TransitionToAddressInputFieldArrangement();
        $migration->update($this->connection);

        $this->assertNewConfiguration();

        $this->connection->update('system_config', [
            'configuration_value' => '{"_value": "city-state-zip"}',
        ], [
            'configuration_key' => Migration1742484083TransitionToAddressInputFieldArrangement::NEW_CONFIG_KEY,
            'sales_channel_id' => null,
        ]);

        $migration->update($this->connection);

        $newConfiguration = $this->connection->fetchAllAssociativeIndexed(
            'SELECT sales_channel_id, configuration_value FROM system_config WHERE configuration_key = ?',
            [Migration1742484083TransitionToAddressInputFieldArrangement::NEW_CONFIG_KEY]
        );
        foreach ($newConfiguration as $uuid => $c) {
            $newConfiguration[$uuid]['configuration_value'] = json_decode($c['configuration_value'], true);
        }

        static::assertSame([
            '' => ['configuration_value' => ['_value' => 'city-state-zip']],
            Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL) => ['configuration_value' => ['_value' => 'city-zip-state']],
        ], $newConfiguration);
    }

    private function revertMigration(): void
    {
        $this->connection->delete('system_config', ['configuration_key' => Migration1742484083TransitionToAddressInputFieldArrangement::NEW_CONFIG_KEY]);
    }

    private function prepareSystemConfig(): void
    {
        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => Migration1742484083TransitionToAddressInputFieldArrangement::OLD_CONFIG_KEY,
            'configuration_value' => json_encode(['_value' => true]),
            'sales_channel_id' => null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => Migration1742484083TransitionToAddressInputFieldArrangement::OLD_CONFIG_KEY,
            'configuration_value' => json_encode(['_value' => false]),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function assertNewConfiguration(): void
    {
        $newConfiguration = $this->connection->fetchAllAssociativeIndexed(
            'SELECT sales_channel_id, configuration_value FROM system_config WHERE configuration_key = ?',
            [Migration1742484083TransitionToAddressInputFieldArrangement::NEW_CONFIG_KEY]
        );
        foreach ($newConfiguration as $uuid => $c) {
            $newConfiguration[$uuid]['configuration_value'] = json_decode($c['configuration_value'], true);
        }

        static::assertSame([
            '' => ['configuration_value' => ['_value' => 'zip-city-state']],
            Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL) => ['configuration_value' => ['_value' => 'city-zip-state']],
        ], $newConfiguration);
    }
}
