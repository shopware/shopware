<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CompanyAccountNameFields;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1788503141CompanyAccountNameFieldsConfig;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1788503141CompanyAccountNameFieldsConfig::class)]
class Migration1788503141CompanyAccountNameFieldsConfigTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        foreach (self::keys() as [$key]) {
            $this->connection->delete('system_config', ['configuration_key' => $key]);
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1788503141, (new Migration1788503141CompanyAccountNameFieldsConfig())->getCreationTimestamp());
    }

    #[DataProvider('keys')]
    public function testMigrationWritesTheDefaultOnce(string $key): void
    {
        $migration = new Migration1788503141CompanyAccountNameFieldsConfig();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $values = $this->values($key);
        static::assertCount(1, $values);
        static::assertSame(['_value' => true], reset($values));
    }

    #[DataProvider('keys')]
    public function testMigrationKeepsAnExistingChoice(string $key): void
    {
        $migration = new Migration1788503141CompanyAccountNameFieldsConfig();
        $migration->update($this->connection);

        $id = array_key_first($this->values($key));
        static::assertIsString($id);

        $this->connection->update(
            'system_config',
            ['configuration_value' => '{"_value": false}'],
            ['id' => Uuid::fromHexToBytes($id)]
        );

        $migration->update($this->connection);

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
            $this->connection->fetchAllKeyValue(
                'SELECT LOWER(HEX(`id`)), `configuration_value` FROM `system_config` WHERE `configuration_key` = ?',
                [$key],
            )
        );
    }
}
