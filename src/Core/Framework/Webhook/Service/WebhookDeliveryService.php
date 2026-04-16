<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Domain orchestrator for webhook delivery with outbox-owned retries.
 *
 * Owns the full delivery lifecycle: persist → route (sync/async) → send HTTP → mark success/retry/failed.
 *
 * @internal
 */
#[Package('framework')]
class WebhookDeliveryService
{
    /**
     * Matches the 5-entry delay table in RetryDelayCalculator::RETRY_DELAYS.
     * Attempts 1..5 each schedule a retry; attempt 6 is terminal (markFailed + failure strategy).
     */
    private const MAX_RETRIES = 5;

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
     * Batch entry point from WebhookManager.
     *
     * When admin worker is enabled (or $forceSynchronous), delivers synchronously via parallel Guzzle Pool (bypasses the transport sender — persists outbox entries directly).
     * For the admin worker: we need the behavior to guarantee that the webhooks are sent, because otherwise we cannot make sure that the webhooks will ever be sent out.
     * For the forceSynchronous: this is added to preserve current behavior with lifecycle events - we won't support retires initially, because that would be an API
     * change in behavior.
     *
     * @param list<WebhookEventMessage> $messages
     * @param bool $forceSynchronous @deprecated tag:v6.8.0 — will be removed. Forces synchronous
     *                               delivery for app lifecycle events (AppDeletedEvent, AppChangedEvent, AppPermissionsUpdated)
     *                               to preserve race-condition prevention semantics from the legacy path. Once removed,
     *                               all deliveries go through the async path with outbox-owned retries.
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
        $entry = $this->outboxEventRepository->markRunning($message->getWebhookEventId());
        $executionCount = $entry !== null ? $entry->executionCount : 1;

        $request = $this->buildRequest($message);
        $result = $this->webhookClient->send($request);

        try {
            $this->handleResult($message->getWebhookEventId(), $message->getWebhookId(), $executionCount, $request, $result);
        } catch (\JsonException $e) {
            $this->logger->error('Webhook delivery response encoding failed for event {eventId}', [
                'eventId' => $message->getWebhookEventId(),
                'webhookId' => $message->getWebhookId(),
                'exception' => $e,
            ]);
            $this->outboxEventRepository->markFailed($message->getWebhookEventId());
        }
    }

    /**
     * Delivers a batch of messages synchronously via parallel Guzzle Pool.
     * Persists outbox entries directly (bypasses transport sender).
     *
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

        foreach ($messages as $message) {
            $this->outboxEventRepository->ensureOutboxEntry(new OutboxInsert(
                $message->getWebhookEventId(),
                $message->getWebhookId(),
                Hasher::hashBinary($message->getPartitionKey(), 'xxh128'),
                serialize($message),
            ));
            $entry = $this->outboxEventRepository->markRunning($message->getWebhookEventId());
            $messagesByEventId[$message->getWebhookEventId()] = $message;
            $executionCounts[$message->getWebhookEventId()] = $entry !== null ? $entry->executionCount : 1;
            $requests[$message->getWebhookEventId()] = $this->buildRequest($message);
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
        if ($executionCount > self::MAX_RETRIES) {
            $this->outboxEventRepository->markFailed($eventLogId, $response);
            $this->applyFailureStrategy($webhookId);

            return;
        }

        $retryAt = $this->retryDelayCalculator->computeNextRetryAt($executionCount);
        $this->outboxEventRepository->markPendingRetry($eventLogId, $retryAt, $response);
    }

    private function applyFailureStrategy(string $webhookId): void
    {
        $newCount = $this->webhookStateRepository->incrementErrorCount($webhookId);

        if ($this->failureStrategy === WebhookFailureStrategy::DisableOnThreshold && $newCount >= WebhookFailureStrategy::MAX_ERROR_COUNT) {
            $this->webhookStateRepository->deactivate($webhookId);
        }
    }

    private function buildRequest(WebhookEventMessage $message): WebhookRequest
    {
        return $this->appPayloadServiceHelper->createWebhookRequest(
            $message->getPayload(),
            $message->getUrl(),
            $message->getShopwareVersion(),
            WebhookClient::CONNECT_TIMEOUT,
            WebhookClient::REQUEST_TIMEOUT,
            $message->getSecret(),
            $message->getLanguageId(),
            $message->getUserLocale(),
            $message->getWebhookHeaders(),
        );
    }
}
