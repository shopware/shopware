<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\SystemCheck;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\SystemCheckResultStorage;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemCheckResultStorage::class)]
class SystemCheckResultStorageTest extends TestCase
{
    public function testAFailingWriteDoesNotFailTheCheckRun(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('transactional')->willThrowException(static::createStub(TableNotFoundException::class));

        $storage = new SystemCheckResultStorage($connection, new MockClock());
        $storage->save([new Result('AppUrlVerification', Status::OK, 'APP_URL points to this shop', true)]);
    }
}
