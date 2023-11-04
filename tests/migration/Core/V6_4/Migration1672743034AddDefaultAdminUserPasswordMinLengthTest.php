<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1672743034AddDefaultAdminUserPasswordMinLength;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1672743034AddDefaultAdminUserPasswordMinLength
 */
class Migration1672743034AddDefaultAdminUserPasswordMinLengthTest extends TestCase
{
    use MigrationTestTrait;

    private const CONFIG_KEY = 'core.userPermission.passwordMinLength';

    public function testConfigValueForUseDefaultCookiesNotification(): void
    {
        $migration = new Migration1672743034AddDefaultAdminUserPasswordMinLength();
        $connection = KernelLifecycleManager::getConnection();
        $connection->delete('system_config', [
            'configuration_key' => self::CONFIG_KEY,
        ]);

        $migration->update($connection);

        $passwordMinLength = $connection->fetchOne('SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key', [
            'key' => self::CONFIG_KEY,
        ]);
        $passwordMinLength = json_decode($passwordMinLength, true);

        static::assertEquals(8, $passwordMinLength['_value']);
    }

    public function testDoesNotOverwriteValuesWhenAlreadyConfigured(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $connection->delete('system_config', [
            'configuration_key' => self::CONFIG_KEY,
        ]);

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => '{"_value": 12}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $migration = new Migration1672743034AddDefaultAdminUserPasswordMinLength();
        $migration->update($connection);

        $passwordMinLength = $connection->fetchOne('SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key', [
            'key' => self::CONFIG_KEY,
        ]);
        $passwordMinLength = json_decode($passwordMinLength, true);

        static::assertEquals(12, $passwordMinLength['_value']);
    }
}
