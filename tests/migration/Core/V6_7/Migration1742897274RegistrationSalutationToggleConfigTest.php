<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1742897274RegistrationSalutationToggleConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1742897274RegistrationSalutationToggleConfig::class)]
class Migration1742897274RegistrationSalutationToggleConfigTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMigration(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $connection->delete('system_config', ['configuration_key' => 'core.loginRegistration.showSalutation']);

        $migration = new Migration1742897274RegistrationSalutationToggleConfig();
        $migration->update($connection);
        $migration->update($connection);

        $newConfiguration = $connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(`id`)), `configuration_value` FROM `system_config` WHERE `configuration_key` = ?',
            ['core.loginRegistration.showSalutation']
        );
        $id = array_key_first($newConfiguration);

        static::assertCount(1, $newConfiguration);
        static::assertEquals('{"_value": true}', $newConfiguration[$id]);

        $connection->update(
            'system_config',
            [
                'configuration_value' => '{"_value": false}',
            ],
            ['id' => Uuid::fromHexToBytes((string) $id)]
        );

        $migration->update($connection);

        $newConfiguration = $connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(`id`)), `configuration_value` FROM `system_config` WHERE `configuration_key` = ?',
            ['core.loginRegistration.showSalutation']
        );
        $id = array_key_first($newConfiguration);

        static::assertCount(1, $newConfiguration);
        static::assertEquals('{"_value": false}', $newConfiguration[$id]);
    }
}
