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

    public const SYSTEM_KEY = 'core.cart.showTosCheckbox';

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1759390536, (new Migration1759390536CartConfigShowTosCheckbox())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        $connection->delete('system_config', ['configuration_key' => self::SYSTEM_KEY]);

        $migration = new Migration1759390536CartConfigShowTosCheckbox();
        $migration->update($connection);
        $migration->update($connection);

        $newConfiguration = $this->getConditionValues();
        $id = array_key_first($newConfiguration);
        static::assertNotNull($id);

        static::assertCount(1, $newConfiguration);
        static::assertSame(['_value' => false], $newConfiguration[$id]);

        $connection->update(
            'system_config',
            ['configuration_value' => '{"_value": true}'],
            ['id' => Uuid::fromHexToBytes((string) $id)]
        );

        $migration->update($connection);

        $newConfiguration = $this->getConditionValues();
        $id = array_key_first($newConfiguration);
        static::assertNotNull($id);

        static::assertCount(1, $newConfiguration);
        static::assertSame(['_value' => true], $newConfiguration[$id]);
    }

    /**
     * @return array<string, array{'_value': bool}>
     */
    private function getConditionValues(): array
    {
        return array_map(
            static fn (string $json) => json_decode($json, true),
            static::getContainer()->get(Connection::class)->fetchAllKeyValue(
                'SELECT LOWER(HEX(`id`)), `configuration_value` FROM `system_config` WHERE `configuration_key` = :key',
                ['key' => self::SYSTEM_KEY],
            )
        );
    }
}
