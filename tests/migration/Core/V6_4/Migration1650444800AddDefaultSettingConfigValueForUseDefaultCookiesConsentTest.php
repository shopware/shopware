<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1650444800AddDefaultSettingConfigValueForUseDefaultCookiesConsent;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1650444800AddDefaultSettingConfigValueForUseDefaultCookiesConsent
 */
class Migration1650444800AddDefaultSettingConfigValueForUseDefaultCookiesConsentTest extends TestCase
{
    use MigrationTestTrait;

    private const CONFIG_KEY = 'core.basicInformation.useDefaultCookieConsent';

    public function testConfigValueForUseDefaultCookiesNotification(): void
    {
        $migration = new Migration1650444800AddDefaultSettingConfigValueForUseDefaultCookiesConsent();
        $connection = KernelLifecycleManager::getConnection();
        $migration->update($connection);

        $useDefaultCookieConsent = $connection->fetchOne('SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key', [
            'key' => self::CONFIG_KEY,
        ]);
        $useDefaultCookieConsent = json_decode((string) $useDefaultCookieConsent, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($useDefaultCookieConsent['_value']);
    }

    public function testDoesNotOverwriteValuesWhenAlreadyConfigured(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $connection->update('system_config', [
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => \json_encode(['_value' => false]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [
            'configuration_key' => self::CONFIG_KEY,
        ]);

        $migration = new Migration1650444800AddDefaultSettingConfigValueForUseDefaultCookiesConsent();
        $migration->update($connection);

        $useDefaultCookieConsent = $connection->fetchOne('SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key', [
            'key' => self::CONFIG_KEY,
        ]);
        $useDefaultCookieConsent = json_decode((string) $useDefaultCookieConsent, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($useDefaultCookieConsent['_value']);
    }
}
