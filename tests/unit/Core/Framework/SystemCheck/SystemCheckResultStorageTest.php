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
use Shopware\Core\Framework\SystemCheck\SystemCheckResultStorage;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemCheckResultStorage::class)]
class SystemCheckResultStorageTest extends TestCase
{
    public function testAFailingWriteIsLoggedAndDoesNotFailTheCheckRun(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('executeStatement')->willThrowException(static::createStub(TableNotFoundException::class));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('Could not store the system check result', static::arrayHasKey('exception'));

        $storage = new SystemCheckResultStorage($connection, new MockClock(), $logger);
        $storage->save([new Result('AppUrlVerification', Status::OK, 'APP_URL points to this shop', true)], SystemCheckExecutionContext::CLI);
    }

    public function testAResultThatCannotBeStoredDoesNotStopTheOthers(): void
    {
        $connection = $this->createMock(Connection::class);
        // the first result's details cannot be encoded, so only the second one reaches the database
        $connection->expects($this->once())->method('executeStatement');

        $storage = new SystemCheckResultStorage($connection, new MockClock(), static::createStub(LoggerInterface::class));
        $storage->save([
            new Result('Broken', Status::OK, 'fine', true, ['value' => \NAN]),
            new Result('AppUrlVerification', Status::OK, 'APP_URL points to this shop', true),
        ], SystemCheckExecutionContext::CLI);
    }
}
