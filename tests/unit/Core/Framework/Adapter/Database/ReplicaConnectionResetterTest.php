<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Database\ReplicaConnectionResetter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReplicaConnectionResetter::class)]
class ReplicaConnectionResetterTest extends TestCase
{
    public function testSwitchesBackToReplicaWhenConnectedToPrimary(): void
    {
        $connection = $this->createMock(PrimaryReadReplicaConnection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->method('isConnectedToPrimary')->willReturn(true);
        $connection->expects($this->once())->method('ensureConnectedToReplica');

        (new ReplicaConnectionResetter($connection))->reset();
    }

    public function testKeepsConnectionDuringOpenTransaction(): void
    {
        $connection = $this->createMock(PrimaryReadReplicaConnection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(1);
        $connection->method('isConnectedToPrimary')->willReturn(true);
        $connection->expects($this->never())->method('ensureConnectedToReplica');

        (new ReplicaConnectionResetter($connection))->reset();
    }

    public function testDoesNothingWhenAlreadyOnReplica(): void
    {
        $connection = $this->createMock(PrimaryReadReplicaConnection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->method('isConnectedToPrimary')->willReturn(false);
        $connection->expects($this->never())->method('ensureConnectedToReplica');

        (new ReplicaConnectionResetter($connection))->reset();
    }

    public function testIgnoresConnectionWithoutReplica(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method(static::anything());

        (new ReplicaConnectionResetter($connection))->reset();
    }

    public function testResetsBeforeRequestsAndReceivedMessages(): void
    {
        static::assertSame([
            KernelEvents::REQUEST => ['onKernelRequest', 4096],
            WorkerMessageReceivedEvent::class => 'reset',
        ], ReplicaConnectionResetter::getSubscribedEvents());
    }

    public function testIgnoresSubRequests(): void
    {
        $connection = $this->createMock(PrimaryReadReplicaConnection::class);
        $connection->expects($this->never())->method(static::anything());

        $event = $this->createStub(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(false);

        (new ReplicaConnectionResetter($connection))->onKernelRequest($event);
    }

    public function testResetsOnMainRequest(): void
    {
        $connection = $this->createMock(PrimaryReadReplicaConnection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->method('isConnectedToPrimary')->willReturn(true);
        $connection->expects($this->once())->method('ensureConnectedToReplica');

        $event = $this->createStub(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(true);

        (new ReplicaConnectionResetter($connection))->onKernelRequest($event);
    }
}
