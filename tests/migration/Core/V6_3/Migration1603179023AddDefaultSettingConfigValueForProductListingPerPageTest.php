<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1603179023AddDefaultSettingConfigValueForProductListingPerPage;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1603179023AddDefaultSettingConfigValueForProductListingPerPage
 */
class Migration1603179023AddDefaultSettingConfigValueForProductListingPerPageTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    /**
     * @before
     */
    public function initialise(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $this->removeConfig($connection);
    }

    public function testConfigValueIsInsertedCorrectly(): void
    {
        $migration = new Migration1603179023AddDefaultSettingConfigValueForProductListingPerPage();

        $migration->update($this->connection);

        static::assertSame(24, $this->readConfigValue($this->connection));
    }

    private function removeConfig(Connection $connection): void
    {
        $connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` = :config_key;',
            ['config_key' => Migration1603179023AddDefaultSettingConfigValueForProductListingPerPage::CONFIG_KEY]
        );
    }

    private function readConfigValue(Connection $connection): int
    {
        $value = $connection->fetchOne(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :config_key LIMIT 1;',
            ['config_key' => Migration1603179023AddDefaultSettingConfigValueForProductListingPerPage::CONFIG_KEY]
        );

        $jsonValue = json_decode((string) $value, true);
        if (json_last_error() === \JSON_ERROR_NONE) {
            return $jsonValue['_value'];
        }

        throw new \UnexpectedValueException(sprintf('Unexpected value \'%s\' for setting: %s', $value, Migration1603179023AddDefaultSettingConfigValueForProductListingPerPage::CONFIG_KEY));
    }
}
