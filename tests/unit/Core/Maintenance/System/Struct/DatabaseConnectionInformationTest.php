<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Maintenance\MaintenanceException;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;

/**
 * @internal
 */
#[CoversClass(DatabaseConnectionInformation::class)]
class DatabaseConnectionInformationTest extends TestCase
{
    use EnvTestBehaviour;

    public function testValidInformation(): void
    {
        $info = new DatabaseConnectionInformation();
        $info->assign([
            'hostname' => 'localhost',
            'port' => 3306,
            'username' => 'root',
            'password' => 'root',
            'databaseName' => 'shopware',
        ]);

        static::assertSame('localhost', $info->getHostname());
        static::assertSame(3306, $info->getPort());
        static::assertSame('root', $info->getUsername());
        static::assertSame('root', $info->getPassword());
        static::assertSame('shopware', $info->getDatabaseName());
        static::assertNull($info->getSslCaPath());
        static::assertNull($info->getSslCertPath());
        static::assertNull($info->getSslCertKeyPath());
        static::assertNull($info->getSslDontVerifyServerCert());

        static::assertFalse($info->hasAdvancedSetting());

        // is valid, should not throw exception
        $info->validate();

        static::assertSame([
            'url' => 'mysql://root:root@localhost:3306/shopware',
            'charset' => 'utf8mb4',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
            ],
        ], $info->toDBALParameters());

        static::assertSame([
            'url' => 'mysql://root:root@localhost:3306',
            'charset' => 'utf8mb4',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
            ],
        ], $info->toDBALParameters(true));
    }

    public function testWithAdvancedSettings(): void
    {
        $info = new DatabaseConnectionInformation();
        $info->assign([
            'hostname' => 'localhost',
            'port' => 3306,
            'username' => 'root',
            'password' => 'root',
            'databaseName' => 'shopware',
            'sslCaPath' => '/ca-path',
            'sslCertPath' => '/cert-path',
            'sslCertKeyPath' => '/cert-key-path',
            'sslDontVerifyServerCert' => true,
        ]);

        static::assertSame('localhost', $info->getHostname());
        static::assertSame(3306, $info->getPort());
        static::assertSame('root', $info->getUsername());
        static::assertSame('root', $info->getPassword());
        static::assertSame('shopware', $info->getDatabaseName());
        static::assertSame('/ca-path', $info->getSslCaPath());
        static::assertSame('/cert-path', $info->getSslCertPath());
        static::assertSame('/cert-key-path', $info->getSslCertKeyPath());
        static::assertTrue($info->getSslDontVerifyServerCert());

        static::assertTrue($info->hasAdvancedSetting());

        // is valid, should not throw exception
        $info->validate();

        static::assertSame([
            'url' => 'mysql://root:root@localhost:3306/shopware',
            'charset' => 'utf8mb4',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
                \PDO::MYSQL_ATTR_SSL_CA => '/ca-path',
                \PDO::MYSQL_ATTR_SSL_CERT => '/cert-path',
                \PDO::MYSQL_ATTR_SSL_KEY => '/cert-key-path',
                \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            ],
        ], $info->toDBALParameters());
    }

    public function testAssignWithRequestStringValues(): void
    {
        $info = new DatabaseConnectionInformation();
        $info->assign([
            'hostname' => 'localhost',
            'port' => '3307',
            'username' => 'root',
            'password' => 'root',
            'databaseName' => 'shopware',
            'sslDontVerifyServerCert' => 'on',
        ]);

        static::assertSame('localhost', $info->getHostname());
        static::assertSame(3307, $info->getPort());
        static::assertSame('root', $info->getUsername());
        static::assertSame('root', $info->getPassword());
        static::assertSame('shopware', $info->getDatabaseName());
        static::assertNull($info->getSslCaPath());
        static::assertNull($info->getSslCertPath());
        static::assertNull($info->getSslCertKeyPath());
        static::assertTrue($info->getSslDontVerifyServerCert());

        static::assertTrue($info->hasAdvancedSetting());

        // is valid, should not throw exception
        $info->validate();

        static::assertSame([
            'url' => 'mysql://root:root@localhost:3307/shopware',
            'charset' => 'utf8mb4',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
                \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            ],
        ], $info->toDBALParameters());

        static::assertSame([
            'url' => 'mysql://root:root@localhost:3307',
            'charset' => 'utf8mb4',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
                \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            ],
        ], $info->toDBALParameters(true));
    }

    public function testInvalid(): void
    {
        $info = new DatabaseConnectionInformation();
        $info->assign([
            'hostname' => '',
            'port' => 3306,
            'username' => 'root',
            'password' => 'root',
            'databaseName' => 'shopware',
        ]);

        static::assertSame('', $info->getHostname());
        static::assertSame(3306, $info->getPort());
        static::assertSame('root', $info->getUsername());
        static::assertSame('root', $info->getPassword());
        static::assertSame('shopware', $info->getDatabaseName());

        $this->expectException(MaintenanceException::class);
        $this->expectExceptionMessage('Provided database connection information is not valid. Missing parameter "hostname"');
        $info->validate();
    }

    #[DataProvider('dsnProvider')]
    public function testAsDsn(DatabaseConnectionInformation $connectionInformation, bool $withoutDB, string $expectedDsn): void
    {
        $dsn = $connectionInformation->asDsn($withoutDB);

        static::assertSame($expectedDsn, $dsn);
    }

    public static function dsnProvider(): \Generator
    {
        yield 'with database' => [
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'username' => 'root',
                'password' => 'root',
                'databaseName' => 'shopware',
            ]),
            false,
            'mysql://root:root@localhost:3306/shopware',
        ];

        yield 'without database' => [
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'username' => 'root',
                'password' => 'root',
                'databaseName' => 'shopware',
            ]),
            true,
            'mysql://root:root@localhost:3306',
        ];

        yield 'without password' => [
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'username' => 'root',
                'databaseName' => 'shopware',
            ]),
            false,
            'mysql://root@localhost:3306/shopware',
        ];

        yield 'without password and user' => [
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'databaseName' => 'shopware',
            ]),
            false,
            'mysql://localhost:3306/shopware',
        ];

        yield 'special chars in password' => [
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'mysql',
                'port' => 3306,
                'username' => 'root',
                'password' => 'ultra?secure#',
                'databaseName' => 'shopware',
            ]),
            false,
            'mysql://root:ultra%3Fsecure%23@mysql:3306/shopware',
        ];
    }

    /**
     * @param array<string, string|bool> $env
     */
    #[DataProvider('validEnvProvider')]
    public function testFromEnv(array $env, DatabaseConnectionInformation $expected): void
    {
        $this->setEnvVars($env);

        $info = DatabaseConnectionInformation::fromEnv();

        static::assertSame($expected->getVars(), $info->getVars());
    }

    public static function validEnvProvider(): \Generator
    {
        yield 'only database' => [
            [
                'DATABASE_URL' => 'mysql://root:root@localhost:3306/shopware',
            ],
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'username' => 'root',
                'password' => 'root',
                'databaseName' => 'shopware',
            ]),
        ];

        yield 'advanced settings' => [
            [
                'DATABASE_URL' => 'mysql://root:root@localhost:3306/shopware',
                'DATABASE_SSL_CA' => '/ca-path',
                'DATABASE_SSL_CERT' => '/cert-path',
                'DATABASE_SSL_KEY' => '/cert-key-path',
                'DATABASE_SSL_DONT_VERIFY_SERVER_CERT' => true,
            ],
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'username' => 'root',
                'password' => 'root',
                'databaseName' => 'shopware',
                'sslCaPath' => '/ca-path',
                'sslCertPath' => '/cert-path',
                'sslCertKeyPath' => '/cert-key-path',
                'sslDontVerifyServerCert' => true,
            ]),
        ];

        yield 'without password' => [
            [
                'DATABASE_URL' => 'mysql://root@localhost:3306/shopware',
            ],
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'username' => 'root',
                'databaseName' => 'shopware',
            ]),
        ];

        yield 'without username and password' => [
            [
                'DATABASE_URL' => 'mysql://localhost:3306/shopware',
            ],
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'databaseName' => 'shopware',
            ]),
        ];

        yield 'without port' => [
            [
                'DATABASE_URL' => 'mysql://localhost/shopware',
            ],
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'localhost',
                'port' => 3306,
                'databaseName' => 'shopware',
            ]),
        ];

        yield 'special chars in password' => [
            [
                'DATABASE_URL' => 'mysql://root:ultra%3Fsecure%23@mysql:3306/shopware',
            ],
            (new DatabaseConnectionInformation())->assign([
                'hostname' => 'mysql',
                'port' => 3306,
                'username' => 'root',
                'password' => 'ultra?secure#',
                'databaseName' => 'shopware',
            ]),
        ];
    }

    /**
     * @param array<string, string|bool> $env
     */
    #[DataProvider('invalidEnvProvider')]
    public function testFromEnvWithInvalidEnv(array $env, string $expectedException): void
    {
        $this->setEnvVars($env);

        $this->expectException(MaintenanceException::class);
        $this->expectExceptionMessage($expectedException);
        DatabaseConnectionInformation::fromEnv();
    }

    public static function invalidEnvProvider(): \Generator
    {
        yield 'Database url not set' => [
            [
                'DATABASE_URL' => '',
            ],
            'Environment variable "DATABASE_URL" is not defined.',
        ];

        yield 'invalid database url' => [
            [
                'DATABASE_URL' => 'invalid',
            ],
            'Environment variable "DATABASE_URL" with value "invalid" is not valid: Not a valid DSN.',
        ];

        yield 'Database name not set' => [
            [
                'DATABASE_URL' => 'mysql://root:root@localhost:3306',
            ],
            'Environment variable "DATABASE_URL" with value "mysql://root:root@localhost:3306" is not valid: Not a valid DSN.',
        ];
    }
}
