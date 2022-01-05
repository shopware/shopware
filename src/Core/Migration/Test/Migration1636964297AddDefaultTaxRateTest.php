<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1636964297AddDefaultTaxRate;

class Migration1636964297AddDefaultTaxRateTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
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
        $id = $this->connection->fetchOne('SELECT `id` FROM `tax` WHERE `name` = ? LIMIT 1', ['Standard rate']);

        $migration = new Migration1636964297AddDefaultTaxRate();
        $migration->update($this->connection);

        static::assertSame(Uuid::fromBytesToHex($id), $this->readConfigValue($this->connection));
    }

    private function removeConfig(Connection $connection): void
    {
        $connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` = :config_key;',
            ['config_key' => Migration1636964297AddDefaultTaxRate::CONFIG_KEY]
        );
    }

    private function readConfigValue(Connection $connection): string
    {
        $value = $connection->fetchOne(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :config_key LIMIT 1;',
            ['config_key' => Migration1636964297AddDefaultTaxRate::CONFIG_KEY]
        );

        $jsonValue = \json_decode($value, true, 512, \JSON_THROW_ON_ERROR);

        return $jsonValue['_value'];
    }
}
