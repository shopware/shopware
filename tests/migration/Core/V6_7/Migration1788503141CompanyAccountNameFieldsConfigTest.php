<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CompanyAccountNameFields;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1788503141CompanyAccountNameFieldsConfig;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1788503141CompanyAccountNameFieldsConfig::class)]
class Migration1788503141CompanyAccountNameFieldsConfigTest extends TestCase
{
    use KernelTestBehaviour;

    protected function setUp(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        foreach (self::keys() as [$key]) {
            $connection->delete('system_config', ['configuration_key' => $key]);
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1788503141, (new Migration1788503141CompanyAccountNameFieldsConfig())->getCreationTimestamp());
    }

    #[DataProvider('keys')]
    public function testMigrationWritesTheDefaultOnce(string $key): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $migration = new Migration1788503141CompanyAccountNameFieldsConfig();
        $migration->update($connection);
        $migration->update($connection);

        $values = $this->values($key);
        static::assertCount(1, $values);
        static::assertSame(['_value' => true], reset($values));
    }

    #[DataProvider('keys')]
    public function testMigrationKeepsAnExistingChoice(string $key): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $migration = new Migration1788503141CompanyAccountNameFieldsConfig();
        $migration->update($connection);

        $id = array_key_first($this->values($key));
        static::assertIsString($id);

        $connection->update(
            'system_config',
            ['configuration_value' => '{"_value": false}'],
            ['id' => Uuid::fromHexToBytes($id)]
        );

        $migration->update($connection);

        $values = $this->values($key);
        static::assertCount(1, $values);
        static::assertSame(['_value' => false], reset($values));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function keys(): iterable
    {
        yield 'show' => [CompanyAccountNameFields::CONFIG_SHOW];
        yield 'required' => [CompanyAccountNameFields::CONFIG_REQUIRED];
    }

    /**
     * @return array<string, mixed>
     */
    private function values(string $key): array
    {
        return array_map(
            static fn (string $json) => json_decode($json, true, 512, \JSON_THROW_ON_ERROR),
            self::getContainer()->get(Connection::class)->fetchAllKeyValue(
                'SELECT LOWER(HEX(`id`)), `configuration_value` FROM `system_config` WHERE `configuration_key` = ?',
                [$key],
            )
        );
    }
}
