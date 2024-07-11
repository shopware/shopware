<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('core')]
class KernelTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @deprecated tag:v6.7.0 - remove
     */
    public function testDatabaseTimeZonesAreEqual(): void
    {
        $env = (bool) EnvironmentHelper::getVariable('SHOPWARE_DBAL_TIMEZONE_SUPPORT_ENABLED', false);

        if ($env === false) {
            static::markTestSkipped('Database does not support timezones');
        }

        $c = $this->getContainer()->get(Connection::class);

        static::assertSame($c->fetchOne('SELECT @@session.time_zone'), '+00:00');
    }

    public function testUTCIsAlwaysSetToDatabase(): void
    {
        Feature::skipTestIfInActive('v6.7.0.0', $this);

        $c = $this->getContainer()->get(Connection::class);

        static::assertSame($c->fetchOne('SELECT @@session.time_zone'), '+00:00');
    }
}
