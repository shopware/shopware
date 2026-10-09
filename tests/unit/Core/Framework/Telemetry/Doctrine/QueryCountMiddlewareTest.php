<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Telemetry\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Doctrine\QueryCountDriver;
use Shopware\Core\Framework\Telemetry\Doctrine\QueryCounter;
use Shopware\Core\Framework\Telemetry\Doctrine\QueryCountMiddleware;
use Shopware\Core\Framework\Telemetry\Doctrine\QueryCountStatement;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(QueryCountMiddleware::class)]
class QueryCountMiddlewareTest extends TestCase
{
    public function testWrapReturnsADriverExposingTheSharedCounter(): void
    {
        $counter = new QueryCounter();
        $middleware = new QueryCountMiddleware($counter);

        static::assertSame($counter, $middleware->getCounter());
        static::assertInstanceOf(QueryCountDriver::class, $middleware->wrap(static::createStub(Driver::class)));
    }

    public function testCountsPreparedStatementExecutionsQueriesAndExecs(): void
    {
        $counter = new QueryCounter();
        $connection = $this->wrapConnection($counter);

        $connection->prepare('SELECT 1')->execute();
        $connection->prepare('SELECT 2')->execute();
        $connection->query('SELECT 3');
        $connection->exec('UPDATE foo SET bar = 1');

        static::assertSame(4, $counter->count());
    }

    public function testPreparingWithoutExecutingDoesNotCount(): void
    {
        $counter = new QueryCounter();
        $connection = $this->wrapConnection($counter);

        $statement = $connection->prepare('SELECT 1');

        static::assertInstanceOf(QueryCountStatement::class, $statement);
        static::assertSame(0, $counter->count());
    }

    private function wrapConnection(QueryCounter $counter): DriverConnection
    {
        $result = static::createStub(Result::class);

        $statement = static::createStub(Statement::class);
        $statement->method('execute')->willReturn($result);

        $innerConnection = static::createStub(DriverConnection::class);
        $innerConnection->method('prepare')->willReturn($statement);
        $innerConnection->method('query')->willReturn($result);
        $innerConnection->method('exec')->willReturn(1);

        $driver = static::createStub(Driver::class);
        $driver->method('connect')->willReturn($innerConnection);

        return (new QueryCountMiddleware($counter))->wrap($driver)->connect([]);
    }
}
