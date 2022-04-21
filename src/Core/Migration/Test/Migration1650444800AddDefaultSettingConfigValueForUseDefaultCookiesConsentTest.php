<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1650444800AddDefaultSettingConfigValueForUseDefaultCookiesConsent;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class Migration1650444800AddDefaultSettingConfigValueForUseDefaultCookiesConsentTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private const CONFIG_KEY = 'core.basicInformation.useDefaultCookieConsent';

    private Connection $connection;

    public function testConfigValueForUseDefaultCookiesNotification(): void
    {
        $migration = new Migration1650444800AddDefaultSettingConfigValueForUseDefaultCookiesConsent();
        $connection = $this->getContainer()->get(Connection::class);
        $migration->update($connection);

        $useDefaultCookieConsent = $connection->fetchColumn('SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key', [
            'key' => self::CONFIG_KEY,
        ]);
        $useDefaultCookieConsent = json_decode($useDefaultCookieConsent, true);

        static::assertTrue($useDefaultCookieConsent['_value']);
    }

    public function testDoesNotOverwriteValuesWhenAlreadyConfigured(): void
    {
        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        $systemConfig->set(self::CONFIG_KEY, false);
        $migration = new Migration1650444800AddDefaultSettingConfigValueForUseDefaultCookiesConsent();
        $connection = $this->getContainer()->get(Connection::class);
        $migration->update($connection);

        $useDefaultCookieConsent = $connection->fetchColumn('SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key', [
            'key' => self::CONFIG_KEY,
        ]);
        $useDefaultCookieConsent = json_decode($useDefaultCookieConsent, true);

        static::assertFalse($useDefaultCookieConsent['_value']);
    }
}
