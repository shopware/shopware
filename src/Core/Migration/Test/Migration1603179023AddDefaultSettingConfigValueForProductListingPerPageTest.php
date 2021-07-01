<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\Migration1603179023AddDefaultSettingConfigValueForProductListingPerPage;

class Migration1603179023AddDefaultSettingConfigValueForProductListingPerPageTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
    }

    /**
     * @before
     */
    public function initialise(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

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
        $connection->executeUpdate(
            'DELETE FROM `system_config` WHERE `configuration_key` = :config_key;',
            ['config_key' => Migration1603179023AddDefaultSettingConfigValueForProductListingPerPage::CONFIG_KEY]
        );
    }

    private function readConfigValue(Connection $connection): int
    {
        $value = $connection->fetchColumn(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :config_key LIMIT 1;',
            ['config_key' => Migration1603179023AddDefaultSettingConfigValueForProductListingPerPage::CONFIG_KEY]
        );

        $jsonValue = json_decode($value, true);
        if (json_last_error() === \JSON_ERROR_NONE) {
            return $jsonValue['_value'];
        }

        throw new \UnexpectedValueException(sprintf('Unexpected value \'%s\' for setting: %s', $value, Migration1603179023AddDefaultSettingConfigValueForProductListingPerPage::CONFIG_KEY));
    }
}
