<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1759390536CartConfigShowTosCheckbox;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1759390536CartConfigShowTosCheckbox::class)]
class Migration1759390536CartConfigShowTosCheckboxTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMigration(): void
    {
        $key = 'core.cart.showTosCheckbox';

        $connection = self::getContainer()->get(Connection::class);

        $connection->delete('system_config', ['configuration_key' => $key]);

        $migration = new Migration1759390536CartConfigShowTosCheckbox();
        $migration->update($connection);
        $migration->update($connection);

        $newConfiguration = $this->getConditionValues($key);
        $id = array_key_first($newConfiguration);

        static::assertCount(1, $newConfiguration);
        static::assertSame(['_value' => true], $newConfiguration[$id]);

        $connection->update(
            'system_config',
            ['configuration_value' => '{"_value": false}'],
            ['id' => Uuid::fromHexToBytes((string) $id)]
        );

        $migration->update($connection);

        $newConfiguration = $this->getConditionValues($key);
        $id = array_key_first($newConfiguration);

        static::assertCount(1, $newConfiguration);
        static::assertSame(['_value' => false], $newConfiguration[$id]);
    }

    /**
     * @return array<string, array{'_value': bool}>
     */
    private function getConditionValues(string $key): array
    {
        return array_map(
            static fn (string $json) => json_decode($json, true),
            static::getContainer()->get(Connection::class)->fetchAllKeyValue(
                'SELECT LOWER(HEX(`id`)), `configuration_value` FROM `system_config` WHERE `configuration_key` = :key',
                ['key' => $key],
            )
        );
    }
}
