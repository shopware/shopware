<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1696262484AddDefaultSendMailOptions;

/**
 * @internal
 */
#[CoversClass(Migration1696262484AddDefaultSendMailOptions::class)]
class Migration1696262484AddDefaultSendMailOptionsTest extends TestCase
{
    public function testValueNotExist(): void
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

        $migration = new Migration1696262484AddDefaultSendMailOptions();
        $migration->update($connection);
        $migration->update($connection);

        static::assertFalse($this->getValue($connection));
    }

    public function testModifiedValue(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        if (\is_string($id = $this->getId($connection))) {
            $sql = 'UPDATE `system_config` SET `configuration_value` = ?, `configuration_key` = \'core.mailerSettings.sendMailOptions\' WHERE `id` = ?;';
            $params = [
                json_encode(['_value' => '-bs']),
                Uuid::fromHexToBytes($id),
            ];
        } else {
            $sql = 'INSERT INTO `system_config` SET `id` = ?, `configuration_value` = ?, `configuration_key` = \'core.mailerSettings.sendMailOptions\', `created_at` = ?;';
            $params = [
                Uuid::randomBytes(),
                json_encode(['_value' => '-bs']),
                (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
        }

        $connection->executeStatement($sql, $params);

        static::assertEquals('-bs', $this->getValue($connection));

        $migration = new Migration1696262484AddDefaultSendMailOptions();
        $migration->update($connection);
        $migration->update($connection);

        static::assertEquals('-bs', $this->getValue($connection));
    }

    public function testDefaultValue(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        if (\is_string($id = $this->getId($connection))) {
            $sql = 'UPDATE `system_config` SET `configuration_value` = ?, `configuration_key` = \'core.mailerSettings.sendMailOptions\' WHERE `id` = ?;';
            $params = [
                json_encode(['_value' => '-t']),
                Uuid::fromHexToBytes($id),
            ];
        } else {
            $sql = 'INSERT INTO `system_config` SET `id` = ?, `configuration_value` = ?, `configuration_key` = \'core.mailerSettings.sendMailOptions\', `created_at` = ?;';
            $params = [
                Uuid::randomBytes(),
                json_encode(['_value' => '-t']),
                (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
        }

        $connection->executeStatement($sql, $params);

        static::assertEquals('-t', $this->getValue($connection));

        $migration = new Migration1696262484AddDefaultSendMailOptions();
        $migration->update($connection);
        $migration->update($connection);

        static::assertEquals('-t -i', $this->getValue($connection));
    }

    private function getValue(Connection $connection): string|false
    {
        $value = $connection->fetchOne(
            'SELECT DISTINCT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key',
            ['key' => 'core.mailerSettings.sendMailOptions']
        );

        if ($value === false) {
            return $value;
        }

        return json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR)['_value'];
    }

    private function getId(Connection $connection): string|bool
    {
        return $connection->fetchOne(
            'SELECT DISTINCT HEX(id) FROM `system_config` WHERE `configuration_key` = :key',
            ['key' => 'core.mailerSettings.sendMailOptions']
        );
    }
}
