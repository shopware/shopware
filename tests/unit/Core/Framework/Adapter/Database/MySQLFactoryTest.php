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

        $connection = MySQLFactory::create();
        $params = $connection->getParams();

        static::assertArrayHasKey('driverOptions', $params);

        // Verify default options are present
        static::assertArrayHasKey(\PDO::ATTR_STRINGIFY_FETCHES, $params['driverOptions']);
        static::assertTrue($params['driverOptions'][\PDO::ATTR_STRINGIFY_FETCHES]);
        static::assertArrayHasKey(\PDO::ATTR_TIMEOUT, $params['driverOptions']);
        static::assertSame(5, $params['driverOptions'][\PDO::ATTR_TIMEOUT]);

        // Verify custom option from DSN is preserved
        static::assertArrayHasKey($customOption, $params['driverOptions']);
        static::assertSame('1', $params['driverOptions'][$customOption]);
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

        $connection = MySQLFactory::create();
        $params = $connection->getParams();

        // Verify primary connection has both default and custom options
        static::assertArrayHasKey('primary', $params);
        static::assertArrayHasKey('driverOptions', $params['primary']);
        static::assertArrayHasKey(\PDO::ATTR_STRINGIFY_FETCHES, $params['primary']['driverOptions']);
        static::assertArrayHasKey($customOption, $params['primary']['driverOptions']);
        static::assertSame('1', $params['primary']['driverOptions'][$customOption]);

        // Verify replica connection has both default and custom options
        static::assertArrayHasKey('replica', $params);
        static::assertCount(1, $params['replica']);
        static::assertArrayHasKey('driverOptions', $params['replica'][0]);
        static::assertArrayHasKey(\PDO::ATTR_STRINGIFY_FETCHES, $params['replica'][0]['driverOptions']);
        static::assertArrayHasKey($replicaCustomOption, $params['replica'][0]['driverOptions']);
        static::assertSame('1', $params['replica'][0]['driverOptions'][$replicaCustomOption]);
    }

    public function testResetConnectionClearsState(): void
    {
        // Use reflection to check the static state
        $reflection = new \ReflectionClass(MySQLFactory::class);
        $connectionProperty = $reflection->getProperty('connection');
        $lastUsedAtProperty = $reflection->getProperty('lastUsedAt');

        // Set some test state
        $mockConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $mockConnection->expects($this->once())->method('close');
        $connectionProperty->setValue(null, $mockConnection);
        $lastUsedAtProperty->setValue(null, time());

        // Call resetConnection
        MySQLFactory::resetConnection();

        // Verify state is cleared
        static::assertNull($connectionProperty->getValue(null));
        static::assertSame(0, $lastUsedAtProperty->getValue(null));
    }

    public function testGetConnectionCreatesNewConnectionWhenNoneExists(): void
    {
        // Reset any existing state first
        MySQLFactory::resetConnection();

        // Use reflection to verify no connection exists
        $reflection = new \ReflectionClass(MySQLFactory::class);
        $connectionProperty = $reflection->getProperty('connection');
        $lastUsedAtProperty = $reflection->getProperty('lastUsedAt');

        static::assertNull($connectionProperty->getValue(null));
        static::assertSame(0, $lastUsedAtProperty->getValue(null));

        // Call getConnection - this will create a real connection
        $connection = MySQLFactory::getConnection();

        // Verify connection was created and cached
        static::assertSame($connection, $connectionProperty->getValue(null));
        static::assertGreaterThan(0, $lastUsedAtProperty->getValue(null));

        // Clean up
        MySQLFactory::resetConnection();
    }

    public function testGetConnectionReturnsCachedConnection(): void
    {
        // Reset any existing state first
        MySQLFactory::resetConnection();

        // Get first connection
        $connection1 = MySQLFactory::getConnection();

        // Get second connection - should be same instance
        $connection2 = MySQLFactory::getConnection();

        static::assertSame($connection1, $connection2);

        // Clean up
        MySQLFactory::resetConnection();
    }

    public function testGetConnectionClosesIdleConnection(): void
    {
        // Reset and set TTL to 1 second via DATABASE_URL
        MySQLFactory::resetConnection();
        $this->setEnvVars(['DATABASE_URL' => 'mysql://root:shopware@127.0.0.1:3306/shopware?idle_connection_ttl=1']);

        // Use reflection to manipulate state
        $reflection = new \ReflectionClass(MySQLFactory::class);
        $connectionProperty = $reflection->getProperty('connection');
        $lastUsedAtProperty = $reflection->getProperty('lastUsedAt');
        $ttlProperty = $reflection->getProperty('idleConnectionTtl');

        // First create a connection to parse the TTL from URL
        MySQLFactory::create();
        static::assertSame(1, $ttlProperty->getValue(null));

        // Create a mock connection
        $mockConnection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $mockConnection->expects($this->once())->method('close');
        $connectionProperty->setValue(null, $mockConnection);

        // Set lastUsedAt to a time in the past (older than TTL)
        $lastUsedAtProperty->setValue(null, time() - 10);

        // Call getConnection - should close old connection and create new one
        $newConnection = MySQLFactory::getConnection();

        // Verify we got a new connection (not the mock)
        static::assertNotSame($mockConnection, $newConnection);

        // Clean up
        MySQLFactory::resetConnection();
        $ttlProperty->setValue(null, 0);
    }

    public function testGetConnectionDoesNotCloseRecentConnection(): void
    {
        // Reset and set TTL to 60 seconds via DATABASE_URL
        MySQLFactory::resetConnection();
        $this->setEnvVars(['DATABASE_URL' => 'mysql://root:shopware@127.0.0.1:3306/shopware?idle_connection_ttl=60']);

        // Use reflection
        $reflection = new \ReflectionClass(MySQLFactory::class);
        $lastUsedAtProperty = $reflection->getProperty('lastUsedAt');
        $ttlProperty = $reflection->getProperty('idleConnectionTtl');

        // Get first connection (this also parses TTL from URL)
        $connection1 = MySQLFactory::getConnection();

        // Verify TTL was parsed
        static::assertSame(60, $ttlProperty->getValue(null));

        // Check lastUsedAt is set
        $lastUsedAt = $lastUsedAtProperty->getValue(null);
        static::assertGreaterThan(0, $lastUsedAt);

        // Get another connection - should be same instance since not idle
        $connection2 = MySQLFactory::getConnection();

        static::assertSame($connection1, $connection2);

        // Clean up
        MySQLFactory::resetConnection();
        $ttlProperty->setValue(null, 0);
    }

    public function testGetConnectionWithTtlDisabled(): void
    {
        // Reset - no idle_connection_ttl in URL means TTL is disabled (default 0)
        MySQLFactory::resetConnection();
        $this->setEnvVars(['DATABASE_URL' => 'mysql://root:shopware@127.0.0.1:3306/shopware']);

        // Use reflection
        $reflection = new \ReflectionClass(MySQLFactory::class);
        $lastUsedAtProperty = $reflection->getProperty('lastUsedAt');
        $ttlProperty = $reflection->getProperty('idleConnectionTtl');

        // Reset TTL to 0 (in case previous test set it)
        $ttlProperty->setValue(null, 0);

        // Create first connection
        $connection1 = MySQLFactory::getConnection();

        // Verify TTL is 0 (disabled)
        static::assertSame(0, $ttlProperty->getValue(null));

        // Manually set lastUsedAt to old time
        $lastUsedAtProperty->setValue(null, time() - 3600); // 1 hour ago

        // Get another connection - should be same instance since TTL is disabled
        $connection2 = MySQLFactory::getConnection();

        static::assertSame($connection1, $connection2);

        // Clean up
        MySQLFactory::resetConnection();
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
