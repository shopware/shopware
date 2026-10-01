<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1775641887AddDoubleOptInResendInterval;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1775641887AddDoubleOptInResendInterval::class)]
class Migration1775641887AddDoubleOptInResendIntervalTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMigration(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $connection->delete('system_config', ['configuration_key' => 'core.loginRegistration.doubleOptInResendInterval']);

        $migration = new Migration1775641887AddDoubleOptInResendInterval();
        $migration->update($connection);
        $migration->update($connection);

        $configValue = $connection->fetchOne(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key',
            ['key' => 'core.loginRegistration.doubleOptInResendInterval']
        );

        static::assertIsString($configValue);

        $configValue = json_decode($configValue, true);
        static::assertIsArray($configValue);
        static::assertArrayHasKey('_value', $configValue);
        static::assertSame(24, $configValue['_value']);
    }
}
