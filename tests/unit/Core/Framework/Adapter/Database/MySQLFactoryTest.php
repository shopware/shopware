<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\MySQLFactory;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;

/**
 * @internal
 */
#[CoversClass(MySQLFactory::class)]
class MySQLFactoryTest extends TestCase
{
    use EnvTestBehaviour;

    public function testMiddlewaresAreUsed(): void
    {
        $conn = MySQLFactory::create([new MyMiddleware()]);

        static::assertInstanceOf(MyDriver::class, $conn->getDriver());
    }

    public function testReplicaConfigurationParsesDsnParameters(): void
    {
        $this->setEnvVars([
            'DATABASE_URL' => 'mysql://user:pass@localhost:3306/shopware',
            'DATABASE_REPLICA_0_URL' => 'mysql://replica_user:replica_pass@replica_host:3307/replica_db',
            'DATABASE_REPLICA_1_URL' => 'mysql://replica_user2:replica_pass2@replica_host2:3308/replica_db2',
        ]);

        $connection = MySQLFactory::create();
        $params = $connection->getParams();

        // Assert connection is not created - we don't want to connect to a real database in unit tests
        static::assertFalse($connection->isConnected());

        // If we get here, the connection was successful and we can test the parameters
        static::assertArrayHasKey('wrapperClass', $params);
        static::assertArrayHasKey('primary', $params);
        static::assertArrayHasKey('replica', $params);
        static::assertCount(2, $params['replica']);

        // Check first replica parameters
        $replica0 = $params['replica'][0];
        static::assertArrayHasKey('host', $replica0);
        static::assertSame('replica_host', $replica0['host']);
        static::assertArrayHasKey('port', $replica0);
        static::assertSame(3307, $replica0['port']);
        static::assertArrayHasKey('user', $replica0);
        static::assertSame('replica_user', $replica0['user']);
        static::assertArrayHasKey('password', $replica0);
        static::assertSame('replica_pass', $replica0['password']);
        static::assertArrayHasKey('dbname', $replica0);
        static::assertSame('replica_db', $replica0['dbname']);
        static::assertArrayHasKey('charset', $replica0);
        static::assertSame('utf8mb4', $replica0['charset']);

        // Check second replica parameters
        $replica1 = $params['replica'][1];
        static::assertArrayHasKey('host', $replica1);
        static::assertSame('replica_host2', $replica1['host']);
        static::assertArrayHasKey('port', $replica1);
        static::assertSame(3308, $replica1['port']);
        static::assertArrayHasKey('user', $replica1);
        static::assertSame('replica_user2', $replica1['user']);
        static::assertArrayHasKey('password', $replica1);
        static::assertSame('replica_pass2', $replica1['password']);
        static::assertArrayHasKey('dbname', $replica1);
        static::assertSame('replica_db2', $replica1['dbname']);
        static::assertArrayHasKey('charset', $replica1);
        static::assertSame('utf8mb4', $replica1['charset']);

        // Verify that parameters are merged correctly
        static::assertArrayHasKey('driver', $replica0);
        static::assertSame('pdo_mysql', $replica0['driver']);
        static::assertArrayHasKey('driverOptions', $params);
        static::assertArrayHasKey('driverOptions', $replica0);
        static::assertSame($replica0['driverOptions'], $params['driverOptions']);

        static::assertArrayHasKey('driver', $replica1);
        static::assertSame('pdo_mysql', $replica1['driver']);
        static::assertArrayHasKey('driverOptions', $replica1);
        static::assertSame($replica1['driverOptions'], $params['driverOptions']);
    }
}

/**
 * @internal
 */
class MyDriver extends AbstractDriverMiddleware
{
}

/**
 * @internal
 */
class MyMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new MyDriver($driver);
    }
}
