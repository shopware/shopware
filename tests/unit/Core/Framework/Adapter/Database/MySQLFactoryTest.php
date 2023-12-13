<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\MySQLFactory;

/**
 * @internal
 */
#[CoversClass(MySQLFactory::class)]
class MySQLFactoryTest extends TestCase
{
    public function testMiddlewaresAreUsed(): void
    {
        $conn = MySQLFactory::create([new MyMiddleware()]);

        static::assertInstanceOf(MyDriver::class, $conn->getDriver());
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
