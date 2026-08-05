<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * The connection is held statically by the Kernel and survives service
 * resets, so in long running runtimes one write would pin every following
 * request handled by the same worker to the primary. Switching back happens
 * at the start of the lifecycle, as late work (e.g. kernel.terminate
 * listeners) may still write to the primary. An event subscriber is used
 * instead of a `kernel.reset` service, because nothing else instantiates
 * this class and the ServicesResetter only resets initialized services.
 *
 * @internal
 */
#[Package('framework')]
class ReplicaConnectionResetter implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // before any listener that might read from the database
            KernelEvents::REQUEST => ['onKernelRequest', 4096],
            WorkerMessageReceivedEvent::class => 'reset',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->reset();
    }

    public function reset(): void
    {
        // Never initiates the first connection: the guards below are pure
        // in-memory checks, and in php-fpm the connection can not point at
        // the primary when kernel.request fires (fresh process, kernel boot
        // only reads). The switch itself is a pointer swap; the only connect
        // it can cause is opening the replica after a write-only message in
        // a long running worker - the same connect the next read would open.
        if (!$this->connection instanceof PrimaryReadReplicaConnection) {
            return;
        }

        // switching during an open transaction would silently discard it
        if ($this->connection->getTransactionNestingLevel() > 0) {
            return;
        }

        if (!$this->connection->isConnectedToPrimary()) {
            return;
        }

        $this->connection->ensureConnectedToReplica();
    }
}
