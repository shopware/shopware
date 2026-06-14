<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEntry;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;
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
        if ($this->isAdminWorkerEnabled || $forceSynchronous) {
            $this->deliverBatch($messages);

            return;
        }

        foreach ($messages as $message) {
            $this->bus->dispatch($message);
        }
    }

    public function deliver(WebhookEventMessage $message): void
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

            $request = $this->buildRequest($message, $entry);
            $httpResult = $this->webhookClient->send($request);
            $this->handleResult($message->getWebhookId(), $entry, $request, $httpResult);
        } catch (DBALException $e) {
            // DB is unavailable — this record will be stuck as RUNNING until next retry.
            $this->logger->error('Webhook delivery persistence failed for event {eventId}', [
                'eventId' => $message->getWebhookEventId(),
                'webhookId' => $message->getWebhookId(),
                'exception' => $e,
            ]);
        }
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

        $results = $this->webhookClient->sendBatch($requests);

        foreach ($results as $eventId => $result) {
            $message = $messagesByEventId[$eventId];
            $request = $requests[$eventId];

            try {
                $this->handleResult($message->getWebhookId(), $entries[$eventId], $request, $result);
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
        $persisted = $this->persistFailureOutcome($entry, $response);
        if (!$persisted) {
            $this->logger->warning('Lease lost while recording webhook failure for event {eventId}', [
                'eventId' => $entry->webhookEventId,
                'webhookId' => $webhookId,
                'sequence' => $entry->sequence,
                'executionCount' => $entry->executionCount,
            ]);

            return;
        }

        // error_count counts failed deliveries, not failed attempts — only bump after retries are exhausted.
        if ($entry->executionCount > self::MAX_RETRIES) {
            $this->webhookHealthService->recordFailure($webhookId, $this->failureStrategy);
        }
    }

    private function persistFailureOutcome(OutboxEntry $entry, ?DeliveryResponse $response = null): bool
    {
        if ($entry->executionCount > self::MAX_RETRIES) {
            return $this->webhookOutboxStore->markFailed($entry, $response);
        }

        $retryAt = $this->retryDelayCalculator->computeNextRetryAt(max(1, $entry->executionCount));

        return $this->webhookOutboxStore->markPendingRetry($entry, $retryAt, $response);
    }
}
