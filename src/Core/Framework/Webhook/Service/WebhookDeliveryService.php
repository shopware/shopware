<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEntry;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Telemetry\WebhookDeliveryAttemptKind;
use Shopware\Core\Framework\Webhook\Telemetry\WebhookDeliveryOutcome;
use Shopware\Core\Framework\Webhook\Telemetry\WebhookDeliveryStatus;
use Shopware\Core\Framework\Webhook\Telemetry\WebhookMetricLabel;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;
use Shopware\Core\Profiling\Profiler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
class WebhookDeliveryService
{
    public const HEADER_EVENT_ID = 'X-Shopware-Event-Id';
    public const HEADER_SEQUENCE = 'X-Shopware-Sequence';
    public const HEADER_ATTEMPT = 'X-Shopware-Attempt';

    // Matches RetryDelayCalculator::RETRY_DELAYS; attempt 6 is terminal.
    public const MAX_RETRIES = 5;

    private const MILLISECONDS_PER_SECOND = 1000;

    private readonly WebhookFailureStrategy $failureStrategy;

    public function __construct(
        private readonly WebhookClient $webhookClient,
        private readonly AppPayloadServiceHelper $appPayloadServiceHelper,
        private readonly WebhookOutboxStore $webhookOutboxStore,
        private readonly RetryDelayCalculator $retryDelayCalculator,
        private readonly MessageBusInterface $bus,
        private readonly WebhookHealthService $webhookHealthService,
        private readonly LoggerInterface $logger,
        private readonly bool $isAdminWorkerEnabled,
        private readonly Meter $meter,
        string $failureStrategy = WebhookFailureStrategy::DisableOnThreshold->value,
    ) {
        $this->failureStrategy = WebhookFailureStrategy::from($failureStrategy);
    }

    /**
     * @param list<WebhookEventMessage> $messages
     * @param bool $forceSynchronous @deprecated tag:v6.8.0 — removed; all deliveries become async.
     */
    public function process(array $messages, bool $forceSynchronous = false): void
    {
        if ($messages === []) {
            return;
        }

        Profiler::trace(
            'webhook::process',
            fn () => $this->doProcess($messages, $forceSynchronous),
            'webhook',
            ['mode' => $this->isAdminWorkerEnabled || $forceSynchronous ? 'admin_worker' : 'messenger'],
        );
    }

    public function deliver(WebhookEventMessage $message): void
    {
        Profiler::trace(
            'webhook::deliver',
            fn () => $this->doDeliver($message),
            'webhook',
        );
    }

    public function buildRequest(WebhookEventMessage $message, OutboxEntry $entry): WebhookRequest
    {
        $payload = $message->getPayload();
        $headers = $message->getWebhookHeaders();
        $headers = array_filter(
            $headers,
            static fn (string $headerName): bool => !\in_array(strtolower($headerName), [
                strtolower(self::HEADER_EVENT_ID),
                strtolower(self::HEADER_SEQUENCE),
                strtolower(self::HEADER_ATTEMPT),
            ], true),
            \ARRAY_FILTER_USE_KEY
        );
        // Rework-only headers: legacy envelopes have no reliable dispatch-order sequence,
        // so we omit them entirely.
        if ($message->isReworkEnvelope()) {
            $headers[self::HEADER_EVENT_ID] = $message->getWebhookEventId();
            $headers[self::HEADER_SEQUENCE] = (string) $entry->sequence;
            $headers[self::HEADER_ATTEMPT] = (string) max(0, $entry->executionCount - 1);

            if (isset($payload['source']) && \is_array($payload['source'])) {
                $payload['source']['sequence'] = $entry->sequence;
            }
        }

        return $this->appPayloadServiceHelper->createWebhookRequest(
            $payload,
            $message->getUrl(),
            $message->getShopwareVersion(),
            WebhookClient::CONNECT_TIMEOUT,
            WebhookClient::REQUEST_TIMEOUT,
            $message->getSecret(),
            $message->getLanguageId(),
            $message->getUserLocale(),
            $headers,
        );
    }

    /**
     * @param list<WebhookEventMessage> $messages
     */
    private function doProcess(array $messages, bool $forceSynchronous): void
    {
        if ($this->isAdminWorkerEnabled || $forceSynchronous) {
            $this->deliverBatch($messages);

            return;
        }

        foreach ($messages as $message) {
            $this->bus->dispatch($message);
        }
    }

    private function doDeliver(WebhookEventMessage $message): void
    {
        try {
            $entry = $this->webhookOutboxStore->markRunning($message->getWebhookEventId());
            if ($entry === null) {
                // Under StreamLease, this should be rare — signals lease loss or crash-recovery re-claim.
                $this->logger->warning('Skipping webhook delivery: lease lost for event {eventId}', [
                    'eventId' => $message->getWebhookEventId(),
                    'webhookId' => $message->getWebhookId(),
                ]);

                return;
            }

            Profiler::trace(
                'webhook::deliver.attempt',
                fn () => $this->doDeliverAttempt($message, $entry),
                'webhook',
                ['attempt' => (string) $entry->executionCount],
            );
        } catch (DBALException $e) {
            // DB is unavailable — this record will be stuck as RUNNING until next retry.
            $this->logger->error('Webhook delivery persistence failed for event {eventId}', [
                'eventId' => $message->getWebhookEventId(),
                'webhookId' => $message->getWebhookId(),
                'exception' => $e,
            ]);
        }
    }

    private function doDeliverAttempt(WebhookEventMessage $message, OutboxEntry $entry): void
    {
        $request = $this->buildRequest($message, $entry);

        $this->emitAttemptStarted($entry);
        $startedAt = microtime(true);
        $httpResult = $this->webhookClient->send($request);
        $this->emitAttemptDuration($httpResult, microtime(true) - $startedAt);

        $this->handleResult($message->getWebhookId(), $entry, $request, $httpResult);
    }

    /**
     * @param list<WebhookEventMessage> $messages
     */
    private function deliverBatch(array $messages): void
    {
        /** @var array<string, WebhookRequest> $requests */
        $requests = [];
        /** @var array<string, WebhookEventMessage> $messagesByEventId */
        $messagesByEventId = [];
        /** @var array<string, OutboxEntry> $entries */
        $entries = [];
        /** @var array<string, int> $batchIndexesByEventId */
        $batchIndexesByEventId = [];

        // Write RUNNING directly so the async receiver can't re-claim the row mid-flight;
        // a concurrent inline caller hits the event_log and gets null here.
        foreach ($messages as $batchIndex => $message) {
            $entry = $this->webhookOutboxStore->recordInflightOutboxEntry(OutboxInsert::fromMessage($message));
            if ($entry === null) {
                continue;
            }
            $messagesByEventId[$message->getWebhookEventId()] = $message;
            $entries[$message->getWebhookEventId()] = $entry;
            $batchIndexesByEventId[$message->getWebhookEventId()] = $batchIndex;
            $requests[$message->getWebhookEventId()] = $this->buildRequest($message, $entry);
        }

        if ($requests === []) {
            return;
        }

        /** @var array<string, float> $startedAtByEventId */
        $startedAtByEventId = [];
        foreach ($entries as $eventId => $entry) {
            $this->emitAttemptStarted($entry);
            $startedAtByEventId[$eventId] = microtime(true);
        }

        $results = $this->webhookClient->sendBatch($requests);

        foreach ($results as $eventId => $result) {
            $entry = $entries[$eventId];
            $message = $messagesByEventId[$eventId];
            $request = $requests[$eventId];

            $this->emitAttemptDuration($result, microtime(true) - ($startedAtByEventId[$eventId] ?? microtime(true)));

            try {
                $this->handleResult($message->getWebhookId(), $entry, $request, $result);
            } catch (DBALException $e) {
                // DB failure in markSuccess/markPendingRetry/markFailed — don't let one entry block the rest
                $this->logger->error('Webhook delivery result handling failed for event {eventId}', [
                    'eventId' => $eventId,
                    'webhookId' => $message->getWebhookId(),
                    'partitionKey' => $message->getPartitionKey(),
                    'batchIndex' => $batchIndexesByEventId[$eventId],
                    'exception' => $e,
                ]);
            }
        }
    }

    private function handleResult(string $webhookId, OutboxEntry $entry, WebhookRequest $request, WebhookResult $result): void
    {
        $response = DeliveryResponse::from($request, $result);

        if ($result->successful()) {
            // a stale-success on a stolen lease must not reset error_count.
            if ($this->webhookOutboxStore->markSuccess($entry, $response)) {
                $this->webhookHealthService->resetErrorCount($webhookId);
                $this->emitDeliveryPersisted($entry, WebhookEventLogDefinition::STATUS_SUCCESS);

                return;
            }

            $this->logger->warning('Lease lost after successful webhook delivery for event {eventId}', [
                'eventId' => $entry->webhookEventId,
                'webhookId' => $webhookId,
                'sequence' => $entry->sequence,
                'executionCount' => $entry->executionCount,
            ]);

            return;
        }

        $this->handleFailure($webhookId, $entry, $response);
    }

    private function handleFailure(string $webhookId, OutboxEntry $entry, ?DeliveryResponse $response): void
    {
        $status = $this->persistFailureOutcome($entry, $response);
        if ($status === null) {
            $this->logger->warning('Lease lost while recording webhook failure for event {eventId}', [
                'eventId' => $entry->webhookEventId,
                'webhookId' => $webhookId,
                'sequence' => $entry->sequence,
                'executionCount' => $entry->executionCount,
            ]);

            return;
        }

        // error_count counts failed deliveries, not failed attempts — only bump after retries are exhausted.
        if ($status === WebhookEventLogDefinition::STATUS_FAILED && $entry->executionCount > self::MAX_RETRIES) {
            $this->webhookHealthService->recordFailure($webhookId, $this->failureStrategy);
        }

        $this->emitDeliveryPersisted($entry, $status);
    }

    /**
     * @return WebhookEventLogDefinition::STATUS_FAILED|WebhookEventLogDefinition::STATUS_PENDING_RETRY|null
     */
    private function persistFailureOutcome(OutboxEntry $entry, ?DeliveryResponse $response = null): ?string
    {
        if ($entry->executionCount > self::MAX_RETRIES) {
            return $this->webhookOutboxStore->markFailed($entry, $response)
                ? WebhookEventLogDefinition::STATUS_FAILED
                : null;
        }

        $retryAt = $this->retryDelayCalculator->computeNextRetryAt(max(1, $entry->executionCount));

        return $this->webhookOutboxStore->markPendingRetry($entry, $retryAt, $response)
            ? WebhookEventLogDefinition::STATUS_PENDING_RETRY
            : null;
    }

    private function emitAttemptStarted(OutboxEntry $entry): void
    {
        $this->meter->emit(new ConfiguredMetric(
            name: 'webhook.delivery.attempt.total',
            value: 1,
            labels: [WebhookMetricLabel::KIND->value => WebhookDeliveryAttemptKind::fromExecutionCount($entry->executionCount)->value],
        ));
    }

    private function emitAttemptDuration(WebhookResult $result, float $elapsed): void
    {
        $duration = max(0.0, $elapsed) * self::MILLISECONDS_PER_SECOND;

        $this->meter->emit(new ConfiguredMetric(
            name: 'webhook.delivery.duration',
            value: $duration,
            labels: [WebhookMetricLabel::OUTCOME->value => WebhookDeliveryOutcome::fromStatusCode($result->statusCode)->value],
        ));
    }

    /**
     * @param WebhookEventLogDefinition::STATUS_SUCCESS|WebhookEventLogDefinition::STATUS_FAILED|WebhookEventLogDefinition::STATUS_PENDING_RETRY $status
     */
    private function emitDeliveryPersisted(OutboxEntry $entry, string $status): void
    {
        $statusLabel = WebhookDeliveryStatus::from($status);

        $this->meter->emit(new ConfiguredMetric(
            name: 'webhook.delivery.total',
            value: 1,
            labels: [WebhookMetricLabel::STATUS->value => $statusLabel->value],
        ));

        if (!$statusLabel->isTerminal()) {
            return;
        }

        $now = time();
        $eventLogCreatedAt = $entry->eventLogCreatedAt;
        $drainTime = max(0, $now - $eventLogCreatedAt->getTimestamp());
        $this->meter->emit(new ConfiguredMetric(
            name: 'webhook.delivery.time_to_drain_seconds',
            value: $drainTime,
            labels: [WebhookMetricLabel::STATUS->value => $statusLabel->value],
        ));
    }
}
