<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * Switches a primary/replica connection back to the replica after a handled
 * request or message. The connection is held statically by the Kernel and
 * survives kernel service resets, so in long running runtimes a request that
 * wrote to the primary would otherwise pin every following request handled by
 * the same worker to the primary.
 *
 * This is an event subscriber instead of a `kernel.reset` service on purpose:
 * the ServicesResetter only resets services that were initialized during the
 * request, and nothing else instantiates this class, so a reset-only service
 * would never run (and would even be dropped from the container as unused).
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
            KernelEvents::TERMINATE => 'reset',
            WorkerMessageHandledEvent::class => 'reset',
            WorkerMessageFailedEvent::class => 'reset',
        ];
    }

    public function reset(): void
    {
        if (!$this->connection instanceof PrimaryReadReplicaConnection) {
            return;
        }

        // An open transaction at this point is a bug in the request handling;
        // switching the connection now would silently discard it.
        if ($this->connection->getTransactionNestingLevel() > 0) {
            return;
        }

        if (!$this->connection->isConnectedToPrimary()) {
            return;
        }

        $this->connection->ensureConnectedToReplica();
    }
}
