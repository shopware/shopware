<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Transport;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\RetryableException;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEntry;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Outbox\StreamLease;
use Shopware\Core\Framework\Webhook\Outbox\StreamLockService;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Claims one webhook-delivery partition at a time via SKIP LOCKED and yields every due
 * entry on that partition as a Messenger Envelope. A single `get()` call drains up to
 * `MAX_MESSAGES_PER_LEASE` messages before the partition is released for fairness.
 *
 * @internal
 */
#[Package('framework')]
class MySQLWebhookReceiver implements ReceiverInterface, KeepaliveReceiverInterface, ResetInterface
{
    /**
     * Worst-case `MAX_MESSAGES_PER_LEASE * REQUEST_TIMEOUT` (10 × 20s = 200s) stays below
     * `LEASE_SECONDS` with a 40s margin for commit/ack overhead, so the lease cannot
     * expire mid-batch under the documented per-request timeout.
     */
    public const LEASE_SECONDS = 240;
    public const MAX_MESSAGES_PER_LEASE = 10;

    private const CLAIMABLE_STATUSES = [
        WebhookEventLogDefinition::STATUS_QUEUED,
        WebhookEventLogDefinition::STATUS_PENDING_RETRY,
    ];

    private const MAX_CONSECUTIVE_DEADLOCKS = 3;

    /**
     * Throttle per-partition crash-recovery passes within a worker process.
     */
    private const CRASH_RECOVERY_COOLDOWN_SECONDS = 60;

    private ?StreamLease $currentLease = null;

    private int $messagesDeliveredInLease = 0;

    private int $consecutiveDeadlocks = 0;

    /**
     * @var array<string, float> partition_key (binary) → timestamp of last crash-recovery pass
     */
    private array $lastCrashRecoveryAt = [];

    private readonly string $workerId;

    public function __construct(
        private readonly StreamLockService $lockService,
        private readonly OutboxEventRepository $outbox,
        private readonly WebhookDeliveryService $deliveryService,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
        $this->workerId = Uuid::randomHex();
    }

    public function get(): iterable
    {
        try {
            yield from $this->fetch();
            $this->consecutiveDeadlocks = 0;
        } catch (RetryableException $e) {
            $this->logger->info('Webhook receiver hit a transient DB contention; retrying next tick', [
                'consecutiveDeadlocks' => $this->consecutiveDeadlocks + 1,
                'workerId' => $this->workerId,
                'exception' => $e,
            ]);

            // Surface a real contention storm; endless empty polls would mask a DB problem.
            if (++$this->consecutiveDeadlocks >= self::MAX_CONSECUTIVE_DEADLOCKS) {
                $this->consecutiveDeadlocks = 0;

                /** @phpstan-ignore shopware.domainException (Symfony Messenger's worker contract requires TransportException for transport-layer failures.) */
                throw new TransportException($e->getMessage(), 0, $e);
            }
        } catch (DBALException $e) {
            /** @phpstan-ignore shopware.domainException (Symfony Messenger's worker contract requires TransportException for transport-layer failures.) */
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function ack(Envelope $envelope): void
    {
        // No-op: WebhookDeliveryService::deliver() already persisted terminal state
        // (markSuccess / markPendingRetry / markFailed).
    }

    public function reject(Envelope $envelope): void
    {
        $message = $envelope->getMessage();
        if (!$message instanceof WebhookEventMessage) {
            return;
        }

        try {
            $this->deliveryService->handleRejectedDelivery($message->getWebhookEventId());
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to reset rejected webhook delivery', [
                'webhookEventId' => $message->getWebhookEventId(),
                'workerId' => $this->workerId,
                'exception' => $e,
            ]);
        }
    }

    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        if ($this->currentLease === null) {
            return;
        }

        // Honour Symfony's minimum-duration contract without ever shrinking the lease.
        // The common case (5s SIGALRM vs a healthy 240s lease) becomes a no-op — skipping
        // the DB round-trip for the ~47 same-value UPDATEs that would otherwise fire per
        // lease lifetime. Null falls back to LEASE_SECONDS.
        $remaining = $this->currentLease->expiresAt->getTimestamp() - $this->clock->now()->getTimestamp();
        $desired = $seconds ?? self::LEASE_SECONDS;
        if ($desired <= $remaining) {
            return;
        }

        $renewed = $this->lockService->heartbeat($this->currentLease, $desired);
        if ($renewed === null) {
            $this->abandonLease();

            return;
        }

        $this->currentLease = $renewed;
    }

    /**
     * Called by the Messenger worker on graceful shutdown / container reset. Releases the
     * partition lease so another worker can claim it immediately instead of waiting for
     * the lease to expire naturally.
     */
    public function reset(): void
    {
        $this->releaseLease();
    }

    /**
     * @return \Generator<Envelope>
     */
    private function fetch(): \Generator
    {
        // 1. Rotate for fairness if the batch budget is used up or the lease has aged out.
        if ($this->shouldRotateLease()) {
            $this->releaseLease();
        }

        // 2. Claim or renew the lease. Heartbeat on reuse catches theft from a long handler.
        $lease = $this->currentLease !== null ? $this->renewCurrentLease() : $this->acquireLease();
        if ($lease === null) {
            return;
        }

        // 3. Fetch up to the remaining batch budget.
        $budget = self::MAX_MESSAGES_PER_LEASE - $this->messagesDeliveredInLease;
        $entries = $this->outbox->fetchDue($lease->partitionKey, self::CLAIMABLE_STATUSES, $budget);
        if ($entries === []) {
            // Partition drained — release so another partition gets a turn next tick.
            $this->releaseLease();

            return;
        }

        // 4. Yield one envelope per readable entry. Broken payloads are failed and skipped.
        foreach ($entries as $entry) {
            // Signal-driven `keepalive()` on --keepalive workers can null currentLease
            // mid-batch when the heartbeat detects a stolen lease. Stop yielding a
            // snapshot we no longer own.
            if ($this->currentLease === null) {
                return;
            }
            // Also guard against the lease naturally expiring without a heartbeat firing
            // (--keepalive off or slow worker). The DB lease is what gates partition
            // ownership; if it's past, another worker may already be draining this partition.
            if ($this->clock->now() >= $this->currentLease->expiresAt) {
                $this->abandonLease();

                return;
            }
            $envelope = $this->toEnvelope($entry);
            if ($envelope === null) {
                continue;
            }
            ++$this->messagesDeliveredInLease;
            yield $envelope;
        }
    }

    private function renewCurrentLease(): ?StreamLease
    {
        \assert($this->currentLease !== null);

        // Past our own expiry — the local lease is stale. Drop it and let the next tick
        // re-acquire fresh. This is the same outcome a zero-affected-rows UPDATE would
        // give us, but without the round-trip; under single-worker deployments this just
        // means we fell behind on heartbeats, not that someone else took the partition.
        if ($this->clock->now() >= $this->currentLease->expiresAt) {
            $this->abandonLease();

            return null;
        }

        $renewed = $this->lockService->heartbeat($this->currentLease, self::LEASE_SECONDS);
        if ($renewed === null) {
            $this->abandonLease();

            return null;
        }
        $this->currentLease = $renewed;

        return $renewed;
    }

    private function acquireLease(): ?StreamLease
    {
        $lease = $this->lockService->claimNext($this->workerId, self::LEASE_SECONDS, self::CLAIMABLE_STATUSES);
        if ($lease === null) {
            return null;
        }

        $this->currentLease = $lease;
        $this->messagesDeliveredInLease = 0;
        $this->recoverCrashedDeliveries($lease->partitionKey);

        return $lease;
    }

    /**
     * Reset RUNNING rows on a freshly claimed partition to PENDING_RETRY — they were
     * left behind by a worker that died before transitioning them.
     *
     * Throttled per-worker: within a single worker process, a recent pass on this
     * partition means any rows this worker handled are already in their terminal state,
     * so the multi-table UPDATE would be wasted work. A brand-new worker instance starts
     * with an empty cache and runs on first claim, which is when cross-worker crash
     * recovery actually matters.
     */
    private function recoverCrashedDeliveries(string $partitionKey): void
    {
        $now = (float) $this->clock->now()->format('U.u');
        $lastRun = $this->lastCrashRecoveryAt[$partitionKey] ?? 0.0;

        if ($now - $lastRun < self::CRASH_RECOVERY_COOLDOWN_SECONDS) {
            return;
        }

        $this->outbox->resetRunningForPartition($partitionKey, self::LEASE_SECONDS);
        $this->lastCrashRecoveryAt[$partitionKey] = $now;
    }

    private function toEnvelope(OutboxEntry $entry): ?Envelope
    {
        // fetchDue always populates the blob; the nullable is only for markRunning's
        // state-query return.
        \assert($entry->serializedWebhookMessage !== null);

        // Payload was serialized by us in WebhookTransport::send(). @ silences the
        // invalid-input warning; we handle a false return explicitly below. The
        // allowed_classes allowlist defense-in-depths the DB blob against object-injection
        // if an attacker ever gains SQL-write: a spoofed class deserializes as
        // __PHP_Incomplete_Class, which the instanceof check below rejects.
        /** @phpstan-ignore shopware.unserializeUsage */
        $message = @unserialize($entry->serializedWebhookMessage, ['allowed_classes' => [WebhookEventMessage::class]]);
        if ($message instanceof WebhookEventMessage) {
            // TransportMessageIdStamp enables framework tooling (messenger:failed:show,
            // profiler, SendFailedMessageToFailureTransportMiddleware) to correlate the
            // message with its transport-side identity.
            return (new Envelope($message))->with(new TransportMessageIdStamp($entry->webhookEventId));
        }

        $this->dropBrokenEntry($entry);

        return null;
    }

    private function dropBrokenEntry(OutboxEntry $entry): void
    {
        // Claim the row first so markFailed can transition it; skip if another worker already has it.
        if ($this->outbox->markRunning($entry->webhookEventId) === null) {
            return;
        }

        $this->outbox->markFailed($entry->webhookEventId);

        $this->logger->error('Discarded unreadable webhook delivery', [
            'webhookEventId' => $entry->webhookEventId,
            'workerId' => $this->workerId,
        ]);
    }

    private function shouldRotateLease(): bool
    {
        if ($this->currentLease === null) {
            return false;
        }

        // Batch budget is the only fairness signal. A time-based check against the original
        // acquire time would force rotation even while heartbeats legitimately extend the DB
        // lease; lease-expiry on a non-rotated partition is caught per-yield in fetch().
        return $this->messagesDeliveredInLease >= self::MAX_MESSAGES_PER_LEASE;
    }

    private function releaseLease(): void
    {
        if ($this->currentLease !== null) {
            $this->lockService->release($this->currentLease);
        }
        $this->abandonLease();
    }

    /**
     * Drop local lease state without touching the DB. Used when the lease has already
     * been taken by another worker — our UPDATE would find no matching row anyway.
     */
    private function abandonLease(): void
    {
        $this->currentLease = null;
        $this->messagesDeliveredInLease = 0;
    }
}
