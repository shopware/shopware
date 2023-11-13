<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('core')]
class KernelTest extends TestCase
{
    use KernelTestBehaviour;

    public function testDatabaseTimeZonesAreEqual(): void
    {
        $env = (bool) EnvironmentHelper::getVariable('SHOPWARE_DBAL_TIMEZONE_SUPPORT_ENABLED', false);

        if ($env === false) {
            static::markTestSkipped('Database does not support timezones');
        }

        $c = $this->getContainer()->get(Connection::class);

        static::assertSame(
            $c->fetchOne('SELECT @@session.time_zone'),
            date_default_timezone_get()
        );
    }
}
