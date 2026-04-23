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
use Shopware\Core\Framework\Webhook\WebhookException;
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
            $this->handleResult($message->getWebhookEventId(), $message->getWebhookId(), $entry->executionCount, $request, $httpResult);
        } catch (\JsonException $e) {
            $this->logger->error('Webhook delivery encoding failed for event {eventId}', [
                'eventId' => $message->getWebhookEventId(),
                'webhookId' => $message->getWebhookId(),
                'exception' => $e,
            ]);

            try {
                $this->outboxEventRepository->markFailed($message->getWebhookEventId());
            } catch (DBALException $dbalException) {
                $this->logger->error('Webhook delivery terminal write failed for event {eventId}', [
                    'eventId' => $message->getWebhookEventId(),
                    'webhookId' => $message->getWebhookId(),
                    'exception' => $dbalException,
                ]);
            }
        } catch (DBALException $e) {
            // DB is unavailable — let Messenger reject and retry instead of acking a stuck RUNNING row.
            $this->logger->error('Webhook delivery persistence failed for event {eventId}', [
                'eventId' => $message->getWebhookEventId(),
                'webhookId' => $message->getWebhookId(),
                'exception' => $e,
            ]);

            throw WebhookException::webhookFailedException($message->getWebhookId(), $e);
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
     * Called by the transport's `reject()` when the Messenger handler threw uncaught.
     * MAX_RETRIES is the safety ceiling; past it the row is marked FAILED so a poison
     * message can't loop the partition.
     */
    public function handleRejectedDelivery(string $eventLogId): void
    {
        $executionCount = $this->outboxEventRepository->loadExecutionCount($eventLogId);
        if ($executionCount === null) {
            return;
        }

        $this->persistFailureOutcome($eventLogId, $executionCount);
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
        /** @var array<string, int> $executionCounts */
        $executionCounts = [];

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
            $executionCounts[$message->getWebhookEventId()] = $entry->executionCount;
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
                $this->handleResult($eventId, $message->getWebhookId(), $executionCounts[$eventId], $request, $result);
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

    private function handleResult(string $eventLogId, string $webhookId, int $executionCount, WebhookRequest $request, WebhookResult $result): void
    {
        $response = DeliveryResponse::from($request, $result);

        if ($result->successful()) {
            $this->outboxEventRepository->markSuccess($eventLogId, $response);
            $this->webhookStateRepository->resetErrorCount($webhookId);

            return;
        }

        $this->handleFailure($eventLogId, $webhookId, $executionCount, $response);
    }

    private function handleFailure(string $eventLogId, string $webhookId, int $executionCount, DeliveryResponse $response): void
    {
        $this->webhookStateRepository->recordFailure($webhookId, $this->failureStrategy);
        $this->persistFailureOutcome($eventLogId, $executionCount, $response);
    }

    private function persistFailureOutcome(string $eventLogId, int $executionCount, ?DeliveryResponse $response = null): void
    {
        if ($executionCount > self::MAX_RETRIES) {
            $this->outboxEventRepository->markFailed($eventLogId, $response);

            return;
        }

        $retryAt = $this->retryDelayCalculator->computeNextRetryAt(max(1, $executionCount));
        $this->outboxEventRepository->markPendingRetry($eventLogId, $retryAt, $response);
    }
}
