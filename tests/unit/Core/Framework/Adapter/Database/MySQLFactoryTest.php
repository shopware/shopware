<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
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
        static::assertSame(PrimaryReadReplicaConnection::class, $params['wrapperClass']);
        static::assertArrayHasKey('primary', $params);
        static::assertArrayHasKey('replica', $params);
        static::assertCount(2, $params['replica']);
        static::assertArrayHasKey('driverOptions', $params);

        // Check primary parameters
        $this->assertConnectionParameters($params['primary'], [
            'host' => 'localhost',
            'port' => 3306,
            'user' => 'user',
            'password' => 'pass',
            'dbname' => 'shopware',
            'driver' => 'pdo_mysql',
            'charset' => 'utf8mb4',
            'driverOptions' => $params['driverOptions'],
        ]);

        // Check first replica parameters
        $replica0 = $params['replica'][0];
        $this->assertConnectionParameters($replica0, [
            'host' => 'replica_host',
            'port' => 3307,
            'user' => 'replica_user',
            'password' => 'replica_pass',
            'dbname' => 'replica_db',
            'driver' => 'pdo_mysql',
            'charset' => 'utf8mb4',
            'driverOptions' => $params['driverOptions'],
        ]);

        // Check second replica parameters
        $replica1 = $params['replica'][1];
        $this->assertConnectionParameters($replica1, [
            'host' => 'replica_host2',
            'port' => 3308,
            'user' => 'replica_user2',
            'password' => 'replica_pass2',
            'dbname' => 'replica_db2',
            'driver' => 'pdo_mysql',
            'charset' => 'utf8mb4',
            'driverOptions' => $params['driverOptions'],
        ]);
    }

    /**
     * @param array<string, mixed> $actualParams
     * @param array<string, mixed> $expectedParams
     */
    private function assertConnectionParameters(array $actualParams, array $expectedParams): void
    {
        static::assertArrayHasKey('host', $actualParams);
        static::assertSame($expectedParams['host'], $actualParams['host']);
        static::assertArrayHasKey('port', $actualParams);
        static::assertSame($expectedParams['port'], $actualParams['port']);
        static::assertArrayHasKey('user', $actualParams);
        static::assertSame($expectedParams['user'], $actualParams['user']);
        static::assertArrayHasKey('password', $actualParams);
        static::assertSame($expectedParams['password'], $actualParams['password']);
        static::assertArrayHasKey('dbname', $actualParams);
        static::assertSame($expectedParams['dbname'], $actualParams['dbname']);
        static::assertArrayHasKey('charset', $actualParams);
        static::assertSame($expectedParams['charset'], $actualParams['charset']);
        static::assertArrayHasKey('driverOptions', $actualParams);
        static::assertSame($expectedParams['driverOptions'], $actualParams['driverOptions']);
        static::assertArrayHasKey('driver', $actualParams);
        static::assertSame($expectedParams['driver'], $actualParams['driver']);
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
