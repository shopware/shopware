<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
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

    public function testDriverOptionsFromDsnArePreserved(): void
    {
        // PDO::MYSQL_ATTR_LOCAL_INFILE = 1001 (enable LOAD DATA LOCAL INFILE)
        $customOption = 1001;
        $customValue = 1;

        $this->setEnvVars([
            'DATABASE_URL' => \sprintf(
                'mysql://user:pass@localhost:3306/shopware?driverOptions[%d]=%d',
                $customOption,
                $customValue
            ),
        ]);

        $params = MySQLFactory::create()->getParams();

        static::assertArrayHasKey('driverOptions', $params);

        // Verify default options are present
        static::assertArrayHasKey(\PDO::ATTR_STRINGIFY_FETCHES, $params['driverOptions']);
        static::assertTrue($params['driverOptions'][\PDO::ATTR_STRINGIFY_FETCHES]);
        static::assertArrayHasKey(\PDO::ATTR_TIMEOUT, $params['driverOptions']);
        static::assertSame(5, $params['driverOptions'][\PDO::ATTR_TIMEOUT]);

        // Verify custom option from DSN is preserved
        static::assertArrayHasKey($customOption, $params['driverOptions']);
        static::assertSame($customValue, $params['driverOptions'][$customOption]);
    }

    public function testDriverOptionsFromDsnArePreservedInReplicaConfiguration(): void
    {
        // PDO::MYSQL_ATTR_LOCAL_INFILE = 1001, PDO::MYSQL_ATTR_FOUND_ROWS = 1004
        $customOption = 1001;
        $customValue = 1;
        $replicaCustomOption = 1004;
        $replicaCustomValue = 1;

        $this->setEnvVars([
            'DATABASE_URL' => \sprintf(
                'mysql://user:pass@localhost:3306/shopware?driverOptions[%d]=%d',
                $customOption,
                $customValue
            ),
            'DATABASE_REPLICA_0_URL' => \sprintf(
                'mysql://replica_user:replica_pass@replica_host:3307/replica_db?driverOptions[%d]=%d',
                $replicaCustomOption,
                $replicaCustomValue
            ),
        ]);

        $params = MySQLFactory::create()->getParams();

        // Verify primary connection has both default and custom options
        static::assertArrayHasKey('primary', $params);
        static::assertArrayHasKey('driverOptions', $params['primary']);
        static::assertArrayHasKey(\PDO::ATTR_STRINGIFY_FETCHES, $params['primary']['driverOptions']);
        static::assertArrayHasKey($customOption, $params['primary']['driverOptions']);
        static::assertSame($customValue, $params['primary']['driverOptions'][$customOption]);

        // Verify replica connection has both default and custom options
        static::assertArrayHasKey('replica', $params);
        static::assertCount(1, $params['replica']);
        static::assertArrayHasKey('driverOptions', $params['replica'][0]);
        static::assertArrayHasKey(\PDO::ATTR_STRINGIFY_FETCHES, $params['replica'][0]['driverOptions']);
        static::assertArrayHasKey($replicaCustomOption, $params['replica'][0]['driverOptions']);
        static::assertSame($replicaCustomValue, $params['replica'][0]['driverOptions'][$replicaCustomOption]);
    }

    public function testWrapperClassWithDriverOptions(): void
    {
        $this->setEnvVars([
            'DATABASE_URL' => 'mysql://user:pass@localhost:3306/shopware?wrapperClass=Shopware\Tests\Unit\Core\Framework\Adapter\Database\MyWrapper&driverOptions[x_foo_bar]=3&driverOptions[foo][bar]=true',
        ]);

        $connection = MySQLFactory::create();

        // Assert connection is not created - we don't want to connect to a real database in unit tests
        static::assertFalse($connection->isConnected());

        $params = $connection->getParams();
        static::assertArrayHasKey('wrapperClass', $params);
        static::assertSame(MyWrapper::class, $params['wrapperClass']);
        static::assertArrayHasKey('driverOptions', $params);
        $driverOptions = $params['driverOptions'];
        static::assertArrayHasKey('x_foo_bar', $driverOptions);
        static::assertSame(3, $driverOptions['x_foo_bar']);
        static::assertArrayHasKey('foo', $driverOptions);
        static::assertArrayHasKey('bar', $driverOptions['foo']);
        static::assertTrue($driverOptions['foo']['bar']);
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

/**
 * @internal
 */
class MyWrapper extends Connection
{
}
