<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterRemoval;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Health\HttpErrorClassifier;
use Shopware\Core\Framework\Webhook\Message\HeldDeliveryStamp;
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

    // Matches RetryDelayCalculator::RETRY_DELAYS_IN_SECONDS; attempt 6 is terminal.
    public const MAX_RETRIES = 5;

    private readonly WebhookFailureStrategy $failureStrategy;

    public function __construct(
        private readonly WebhookClient $webhookClient,
        private readonly AppPayloadServiceHelper $appPayloadServiceHelper,
        private readonly WebhookSigningSecretResolver $signingSecretResolver,
        private readonly WebhookOutboxStore $webhookOutboxStore,
        private readonly RetryDelayCalculator $retryDelayCalculator,
        private readonly MessageBusInterface $bus,
        private readonly WebhookHealthService $webhookHealthService,
        private readonly LoggerInterface $logger,
        private readonly HttpErrorClassifier $errorClassifier,
        private readonly bool $isAdminWorkerEnabled,
        string $failureStrategy = WebhookFailureStrategy::DisableOnThreshold->value,
    ) {
        $this->failureStrategy = WebhookFailureStrategy::from($failureStrategy);
    }

    /**
     * @param list<WebhookEventMessage> $messages
     */
    #[ParameterRemoval(version: 'v6.8.0', parameterName: 'forceSynchronous', description: 'All deliveries become asynchronous.')]
    public function process(array $messages, bool $forceSynchronous = false): void
    {
        if ($forceSynchronous) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', '$forceSynchronous is deprecated and all deliveries become asynchronous in v6.8.0.');
        }

        if ($this->isAdminWorkerEnabled || $forceSynchronous) {
            $this->deliverBatch($messages);

            return;
        }

        foreach ($messages as $message) {
            $this->bus->dispatch($message);
        }
    }

    /**
     * @param list<WebhookEventMessage> $messages
     */
    public function hold(array $messages): void
    {
        foreach ($messages as $message) {
            $this->bus->dispatch($message, [new HeldDeliveryStamp()]);
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
            $this->signingSecretResolver->resolve($message),
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

        if (Feature::isActive('WEBHOOKS_REWORK')) {
            $this->handleHealthResult($webhookId, $entry, $response, $result);

            return;
        }

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
        if (!$this->persistFailureOutcome($entry, $response)) {
            $this->logLeaseLost($webhookId, $entry);

            return;
        }

        // error_count counts failed deliveries, not failed attempts — only bump after retries are exhausted.
        if ($entry->executionCount > self::MAX_RETRIES) {
            $this->webhookHealthService->recordLegacyFailure($webhookId, $this->failureStrategy);
        }
    }

    private function handleHealthResult(string $webhookId, OutboxEntry $entry, DeliveryResponse $response, WebhookResult $result): void
    {
        $classification = $this->errorClassifier->classify($result->statusCode ?? 0);

        if ($result->successful() && $classification === ErrorClassification::Success) {
            if ($this->webhookOutboxStore->markSuccess($entry, $response)) {
                $this->webhookHealthService->recordSuccess($webhookId);

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

        // A reclaimed attempt no longer owns either the row or its health evidence.
        if (!$this->webhookOutboxStore->ownsRunningAttempt($entry)) {
            $this->logLeaseLost($webhookId, $entry);

            return;
        }

        $state = $this->webhookHealthService->recordFailure($webhookId, $classification, $entry->executionCount);
        $retryAfter = $classification === ErrorClassification::TransientRateLimit
            ? $this->retryAfterHeader($result)
            : null;

        $this->placeFailedRow($webhookId, $entry, $response, $classification, $state, $retryAfter);
    }

    private function placeFailedRow(
        string $webhookId,
        OutboxEntry $entry,
        DeliveryResponse $response,
        ErrorClassification $classification,
        EndpointState $state,
        ?string $retryAfter,
    ): void {
        // Payload-specific failures, and unfollowed redirects on a healthy endpoint, are final for this row.
        if (!$classification->isTransient() || ($state === EndpointState::Healthy && $classification === ErrorClassification::TransientRedirect)) {
            $this->webhookOutboxStore->markFailed($entry, $response);

            return;
        }

        // Inside an incident the ladder owns re-timing: the row is re-held, not retried.
        if ($state !== EndpointState::Healthy) {
            $this->webhookOutboxStore->markPaused($entry, $response);

            return;
        }

        if (!$this->persistFailureOutcome($entry, $response, $retryAfter)) {
            $this->logLeaseLost($webhookId, $entry);
        }
    }

    private function retryAfterHeader(WebhookResult $result): ?string
    {
        foreach ($result->headers ?? [] as $name => $values) {
            if (strcasecmp($name, 'Retry-After') === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }

    private function logLeaseLost(string $webhookId, OutboxEntry $entry): void
    {
        $this->logger->warning('Lease lost while recording webhook failure for event {eventId}', [
            'eventId' => $entry->webhookEventId,
            'webhookId' => $webhookId,
            'sequence' => $entry->sequence,
            'executionCount' => $entry->executionCount,
        ]);
    }

    private function persistFailureOutcome(OutboxEntry $entry, ?DeliveryResponse $response = null, ?string $retryAfter = null): bool
    {
        if ($entry->executionCount > self::MAX_RETRIES) {
            return $this->webhookOutboxStore->markFailed($entry, $response);
        }

        $retryAt = $this->retryDelayCalculator->computeNextRetryAt(max(1, $entry->executionCount), $retryAfter);

        return $this->webhookOutboxStore->markPendingRetry($entry, $retryAt, $response);
    }
}
