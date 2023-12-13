<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1667208731AddDefaultDeliveryTimeConfigSetting;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1667208731AddDefaultDeliveryTimeConfigSetting::class)]
class Migration1667208731AddDefaultDeliveryTimeConfigSettingTest extends TestCase
{
    use MigrationTestTrait;

    private const SELECT_QUERY = 'SELECT * FROM `system_config` WHERE configuration_key LIKE "core.cart.showDeliveryTime"';

    private const INSERT_QUERY = 'INSERT INTO system_config (`id`, `configuration_key`, `configuration_value`, `created_at`) VALUES (:id, :configKey, :configValue, :createdAt);';

    private const DELETE_QUERY = 'DELETE FROM `system_config` WHERE configuration_key LIKE "core.cart.showDeliveryTime"';

    private const EXPECTED_VALUE_TRUE = '{"_value": true}';
    private const EXPECTED_VALUE_FALSE = '{"_value": false}';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationShowDeliveryTimeOptionSetsDefaultValueToTrue(): void
    {
        $this->connection->executeStatement(self::DELETE_QUERY);

        $ensureNoValueIsset = $this->connection->fetchAssociative(self::SELECT_QUERY);

        static::assertFalse($ensureNoValueIsset);

        $this->executeMigrationUpdate();

        $result = $this->connection->fetchAssociative(self::SELECT_QUERY);

        static::assertIsArray($result, 'No result found');
        static::assertArrayHasKey('configuration_key', $result);
        static::assertArrayHasKey('configuration_value', $result);
        static::assertSame('core.cart.showDeliveryTime', $result['configuration_key']);
        static::assertSame(self::EXPECTED_VALUE_TRUE, $result['configuration_value'], 'Value is not true');
    }

    public function testMigrationShowDeliveryTimeOptionDoesNotOverwriteExistsSetting(): void
    {
        $this->insertSetting();

        $this->executeMigrationUpdate();

        $result = $this->connection->fetchAssociative(self::SELECT_QUERY);

        static::assertIsArray($result, '$result: No result found');
        static::assertArrayHasKey('configuration_value', $result, '$result: Array key configuration_value not found');
        static::assertSame(self::EXPECTED_VALUE_FALSE, $result['configuration_value'], '$result: Setting configuration_value is not false');
    }

    private function insertSetting(): void
    {
        $this->connection->executeStatement(self::DELETE_QUERY);

        $this->connection->executeStatement(self::INSERT_QUERY, [
            'id' => Uuid::randomBytes(),
            'configKey' => 'core.cart.showDeliveryTime',
            'configValue' => self::EXPECTED_VALUE_FALSE,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $ensureValueIsFalse = $this->connection->fetchAssociative(self::SELECT_QUERY);

        static::assertIsArray($ensureValueIsFalse, '$ensureValueIsFalse: No value found');
        static::assertArrayHasKey('configuration_value', $ensureValueIsFalse, '$ensureValueIsFalse: Array key configuration_value not found');
        static::assertSame(self::EXPECTED_VALUE_FALSE, $ensureValueIsFalse['configuration_value'], '$ensureValueIsFalse: Setting configuration_value is not false');
    }

    private function executeMigrationUpdate(): void
    {
        $migration = new Migration1667208731AddDefaultDeliveryTimeConfigSetting();

        $migration->update($this->connection);
        $migration->update($this->connection);
    }
}
