<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\MySQLEnumTypeMappingDriver;
use Shopware\Core\Framework\Adapter\Database\MySQLEnumTypeMappingMiddleware;
use Shopware\Core\Framework\Adapter\Database\MySQLFactory;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;

/**
 * @internal
 */
#[CoversClass(MySQLEnumTypeMappingDriver::class)]
#[CoversClass(MySQLEnumTypeMappingMiddleware::class)]
#[CoversClass(MySQLFactory::class)]
class MySQLFactoryTest extends TestCase
{
    use EnvTestBehaviour;

    public function testMiddlewaresAreUsed(): void
    {
        $conn = MySQLFactory::create([new MyMiddleware()]);

        static::assertInstanceOf(MyDriver::class, $conn->getDriver());
    }

    public function testCreationDoesNotResolveDatabasePlatform(): void
    {
        $this->setEnvVars(['DATABASE_URL' => 'mysql://root:root@127.0.0.1:1/shopware']);

        $connection = MySQLFactory::create();

        $parameters = $connection->getParams();

        static::assertArrayHasKey('url', $parameters);
        static::assertSame('mysql://root:root@127.0.0.1:1/shopware', $parameters['url']);
    }

    public function testRegistersEnumDoctrineTypeMapping(): void
    {
        $this->setEnvVars(['DATABASE_URL' => 'mysql://root:root@127.0.0.1:1/shopware?serverVersion=8.0.31']);

        $platform = MySQLFactory::create()->getDatabasePlatform();

        static::assertTrue($platform->hasDoctrineTypeMappingFor('enum'));
        static::assertSame('string', $platform->getDoctrineTypeMapping('enum'));
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
