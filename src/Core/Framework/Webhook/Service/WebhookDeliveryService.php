<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEntry;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
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
        private readonly OutboxEventRepository $outboxEventRepository,
        private readonly RetryDelayCalculator $retryDelayCalculator,
        private readonly MessageBusInterface $bus,
        private readonly WebhookStateRepository $webhookStateRepository,
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
            $entry = $this->outboxEventRepository->markRunning($message->getWebhookEventId());
            if ($entry === null) {
                return;
            }

            $request = $this->buildRequest($message, $entry);
            $httpResult = $this->webhookClient->send($request);
            $this->handleResult($message->getWebhookEventId(), $message->getWebhookId(), $entry, $request, $httpResult);
        } catch (DBALException $e) {
            // DB is unavailable — this record will be stuck as RUNNING until next retry.
            $this->logger->error('Webhook delivery persistence failed for event {eventId}', [
                'eventId' => $message->getWebhookEventId(),
                'webhookId' => $message->getWebhookId(),
                'exception' => $e,
            ]);
        }
    }

    public function buildRequest(WebhookEventMessage $message, ?OutboxEntry $entry = null): WebhookRequest
    {
        $payload = $message->getPayload();
        $headers = $message->getWebhookHeaders();
        $headers[self::HEADER_EVENT_ID] = $message->getWebhookEventId();

        if ($entry !== null) {
            if (isset($payload['source']) && \is_array($payload['source'])) {
                $payload['source']['sequence'] = $entry->sequence;
            }
            $headers[self::HEADER_SEQUENCE] = (string) $entry->sequence;
            $headers[self::HEADER_ATTEMPT] = (string) max(0, $entry->executionCount - 1);
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

        // Write RUNNING directly so the async receiver can't re-claim the row mid-flight;
        // a concurrent inline caller hits the event_log UCV and gets null here.
        foreach ($messages as $message) {
            $entry = Feature::silent('v6.8.0.0', fn () => $this->outboxEventRepository->ensureOutboxEntry(
                OutboxInsert::fromMessage($message),
                WebhookEventLogDefinition::STATUS_RUNNING,
            ));
            if ($entry === null) {
                continue;
            }
            $messagesByEventId[$message->getWebhookEventId()] = $message;
            $entries[$message->getWebhookEventId()] = $entry;
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
                $this->handleResult($eventId, $message->getWebhookId(), $entries[$eventId], $request, $result);
            } catch (\JsonException|DBALException $e) {
                // \JsonException: non-UTF8 response body in DeliveryResponse::from json_encode
                // DBALException: DB failure in markSuccess/markPendingRetry/markFailed
                // Don't let one entry block the rest
                $this->logger->error('Webhook delivery result handling failed for event {eventId}', [
                    'eventId' => $eventId,
                    'webhookId' => $message->getWebhookId(),
                    'exception' => $e,
                ]);
            }
        }
    }

    private function handleResult(string $eventLogId, string $webhookId, OutboxEntry $entry, WebhookRequest $request, WebhookResult $result): void
    {
        try {
            $response = DeliveryResponse::from($request, $result);
        } catch (\JsonException $e) {
            $this->logger->error('Webhook delivery response serialization failed for event {eventId}', [
                'eventId' => $eventLogId,
                'webhookId' => $webhookId,
                'exception' => $e,
            ]);

            if ($result->successful()) {
                if ($this->outboxEventRepository->markSuccess($eventLogId, null, $entry->executionCount, $entry->sequence)) {
                    $this->webhookStateRepository->resetErrorCount($webhookId);
                }

                return;
            }

            $this->handleFailure($eventLogId, $webhookId, $entry, null);

            return;
        }

        if ($result->successful()) {
            if ($this->outboxEventRepository->markSuccess($eventLogId, $response, $entry->executionCount, $entry->sequence)) {
                $this->webhookStateRepository->resetErrorCount($webhookId);
            }

            return;
        }

        $this->handleFailure($eventLogId, $webhookId, $entry, $response);
    }

    private function handleFailure(string $eventLogId, string $webhookId, OutboxEntry $entry, ?DeliveryResponse $response): void
    {
        if ($this->persistFailureOutcome($eventLogId, $entry, $response)) {
            $this->webhookStateRepository->recordFailure($webhookId, $this->failureStrategy);
        }
    }

    private function persistFailureOutcome(string $eventLogId, OutboxEntry $entry, ?DeliveryResponse $response = null): bool
    {
        if ($entry->executionCount > self::MAX_RETRIES) {
            return $this->outboxEventRepository->markFailed($eventLogId, $response, $entry->executionCount, $entry->sequence);
        }

        $retryAt = $this->retryDelayCalculator->computeNextRetryAt(max(1, $entry->executionCount));

        return $this->outboxEventRepository->markPendingRetry($eventLogId, $retryAt, $response, $entry->executionCount, $entry->sequence);
    }
}
