<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\SystemCheck;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\SystemCheck\SystemCheckResultStore;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemCheckResultStore::class)]
class SystemCheckResultStoreTest extends TestCase
{
    public function testAFailingWriteIsLoggedAndDoesNotFailTheCheckRun(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('executeStatement')->willThrowException(static::createStub(TableNotFoundException::class));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('Could not store the system check result', static::arrayHasKey('exception'));

        $store = new SystemCheckResultStore($connection, new MockClock(), $logger);
        $store->save([new Result('AppUrlVerification', Status::OK, 'APP_URL points to this shop', true)], SystemCheckExecutionContext::CLI);
    }

    public function testAResultThatCannotBeStoredDoesNotStopTheOthers(): void
    {
        $connection = $this->createMock(Connection::class);
        // the first result's details cannot be encoded, so only the second one reaches the database
        $connection->expects($this->once())->method('executeStatement');

        $store = new SystemCheckResultStore($connection, new MockClock(), static::createStub(LoggerInterface::class));
        $store->save([
            new Result('Broken', Status::OK, 'fine', true, ['value' => \NAN]),
            new Result('AppUrlVerification', Status::OK, 'APP_URL points to this shop', true),
        ], SystemCheckExecutionContext::CLI);
    }

    public function testARowThisVersionCannotReadIsLeftOut(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'Known', 'status' => 'OK', 'healthy' => 1, 'message' => 'fine', 'extra' => '[]', 'execution_context' => 'cli', 'checked_at' => '2026-11-24 10:00:00.000'],
            ['name' => 'UnknownStatus', 'status' => 'MAYBE', 'healthy' => 1, 'message' => 'fine', 'extra' => '[]', 'execution_context' => 'cli', 'checked_at' => '2026-11-24 10:00:00.000'],
            ['name' => 'UnknownContext', 'status' => 'OK', 'healthy' => 1, 'message' => 'fine', 'extra' => '[]', 'execution_context' => 'nightly', 'checked_at' => '2026-11-24 10:00:00.000'],
        ]);

        $store = new SystemCheckResultStore($connection, new MockClock(), static::createStub(LoggerInterface::class));

        static::assertSame(['Known'], array_keys($store->all()));
    }
}
