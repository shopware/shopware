<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Service\Outbox\DeliveryExecutor;
use Shopware\Core\Framework\Webhook\Service\Outbox\Dto\DeliveryOutcome;
use Shopware\Core\Framework\Webhook\Service\Outbox\Dto\OutboxEntry;
use Shopware\Core\Framework\Webhook\Service\Outbox\Dto\StreamContext;
use Shopware\Core\Framework\Webhook\Service\Outbox\OutboxConfig;
use Shopware\Core\Framework\Webhook\Service\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Service\Outbox\StreamLockService;

/**
 * @internal
 */
#[Package('framework')]
class WebhookOutboxProcessor
{
    public function __construct(
        private readonly StreamLockService $lockService,
        private readonly OutboxEventRepository $repository,
        private readonly DeliveryExecutor $executor,
        private readonly RelatedWebhooks $relatedWebhooks,
        private readonly ClockInterface $clock,
        private readonly OutboxConfig $config,
    ) {
    }

    /**
     * Drains the outbox by processing pending webhook entries.
     *
     * @return bool TRUE if more work remains (reschedule needed), FALSE if outbox is empty
     */
    public function drain(string $workerId): bool
    {
        $deadline = $this->clock->now()->modify(sprintf('+%d seconds', $this->config->timeLimitSeconds));
        $this->repository->resetStaleEntries();

        $remaining = $this->config->batchSize;

        while ($this->clock->now() < $deadline && $remaining > 0) {
            $stream = $this->lockService->claimNext($workerId);
            if ($stream === null) {
                // No more streams with pending work
                return false;
            }

            try {
                $remaining = $this->processStream($stream, $remaining);
            } finally {
                $this->lockService->release($stream);
            }
        }

        // Exited due to deadline or batch exhaustion - more work likely remains
        return $this->repository->hasPendingWork();
    }

    /**
     * @deprecated tag:v6.8.0 - reason:flush-is-special-case - Will be removed once outbox processing is fully unified.
     *
     * @param list<string> $ids
     * @param list<string> $eventNames
     */
    public function flush(array $ids, array $eventNames): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'The specific explicit flushing of webhooks is deprecated and will be removed.');

        $entries = $this->repository->fetchForFlush($ids, $eventNames);

        foreach ($entries as $entry) {
            $this->repository->markRunning($entry);
            $outcome = $this->executor->attempt(
                $entry,
                $this->config->requestTimeout,
                $this->config->connectTimeout
            );
            $this->persistOutcome($entry, $outcome);
        }
    }

    /**
     * @return int Remaining batch budget after processing
     */
    private function processStream(StreamContext $stream, int $budget): int
    {
        $entries = $this->repository->fetchPendingRetries($stream->partitionKey, $budget);
        $remaining = $budget - count($entries);

        if ($remaining > 0) {
            $entries = array_merge(
                $entries,
                $this->repository->fetchQueued($stream->partitionKey, $remaining)
            );
        }

        foreach ($entries as $entry) {
            if (!$this->lockService->heartbeat($stream)) {
                break;
            }

            $this->repository->markRunning($entry);
            $outcome = $this->executor->attempt(
                $entry,
                $this->config->requestTimeout,
                $this->config->connectTimeout
            );
            $this->persistOutcome($entry, $outcome);
            --$budget;
        }

        return $budget;
    }

    private function persistOutcome(OutboxEntry $entry, DeliveryOutcome $outcome): void
    {
        match ($outcome->status) {
            DeliveryOutcome::STATUS_SUCCESS => $this->handleSuccess($entry, $outcome),
            DeliveryOutcome::STATUS_RETRY => $this->repository->markPendingRetry($entry, $outcome),
            DeliveryOutcome::STATUS_FAILED => $this->handleFailure($entry, $outcome),
            default => null,
        };
    }

    private function handleSuccess(OutboxEntry $entry, DeliveryOutcome $outcome): void
    {
        $this->repository->markSuccess($entry, $outcome);

        $webhookId = $entry->message->getWebhookId();
        try {
            $this->relatedWebhooks->updateRelated($webhookId, ['error_count' => 0], Context::createDefaultContext());
        } catch (\Throwable) {
        }
    }

    private function handleFailure(OutboxEntry $entry, DeliveryOutcome $outcome): void
    {
        $this->repository->markFailed($entry, $outcome);

        $webhookId = $entry->message->getWebhookId();
        $webhookInfo = $this->repository->getWebhookInfo($webhookId);

        if ($webhookInfo === null || !$webhookInfo['active']) {
            return;
        }

        $webhookErrorCount = $webhookInfo['error_count'] + 1;
        $params = ['error_count' => $webhookErrorCount];

        if ($webhookErrorCount >= $this->config->maxWebhookErrorCount) {
            $params = [
                'error_count' => 0,
                'active' => 0,
            ];
        }

        try {
            $this->relatedWebhooks->updateRelated($webhookId, $params, Context::createDefaultContext());
        } catch (\Throwable) {
        }
    }
}
