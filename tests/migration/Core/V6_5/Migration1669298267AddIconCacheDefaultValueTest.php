<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1669298267AddIconCacheDefaultValue;

/**
 * @internal
 */
#[CoversClass(Migration1669298267AddIconCacheDefaultValue::class)]
class Migration1669298267AddIconCacheDefaultValueTest extends TestCase
{
    public function testInsertValue(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        if (\is_string($id = $this->getId($connection))) {
            $sql = 'DELETE FROM `system_config` WHERE `id` = ?;';
            $params = [
                Uuid::fromHexToBytes($id),
            ];
            $connection->executeStatement($sql, $params);
        }

        static::assertFalse($this->getValue($connection));

        $migration = new Migration1669298267AddIconCacheDefaultValue();
        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue($this->getValue($connection));
    }

    public function testUpdateValue(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        if (\is_string($id = $this->getId($connection))) {
            $sql = 'UPDATE `system_config` SET `configuration_value` = ?, `configuration_key` = \'core.storefrontSettings.iconCache\' WHERE `id` = ?;';
            $params = [
                json_encode(['_value' => true]),
                Uuid::fromHexToBytes($id),
            ];
        } else {
            $sql = 'INSERT INTO `system_config` SET `id` = ?, `configuration_value` = ?, `configuration_key` = \'core.storefrontSettings.iconCache\', `created_at` = ?;';
            $params = [
                Uuid::randomBytes(),
                json_encode(['_value' => true]),
                (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
        }

        $connection->executeStatement($sql, $params);

        static::assertTrue($this->getValue($connection));

        $migration = new Migration1669298267AddIconCacheDefaultValue();
        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue($this->getValue($connection));
    }

    private function getValue(Connection $connection): bool
    {
        $value = $connection->fetchOne(
            'SELECT DISTINCT `configuration_value` FROM `system_config` WHERE `configuration_key` = \'core.storefrontSettings.iconCache\';'
        );

        if (\is_bool($value)) {
            return $value;
        }

        return json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR)['_value'];
    }

    private function getId(Connection $connection): string|bool
    {
        return $connection->fetchOne(
            'SELECT DISTINCT HEX(id) FROM `system_config` WHERE `configuration_key` = \'core.storefrontSettings.iconCache\';'
        );
    }
}
