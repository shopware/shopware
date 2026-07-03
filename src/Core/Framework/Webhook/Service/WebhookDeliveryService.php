<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\EndpointHealth;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Health\ErrorClassifier;
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

    // Matches the number of delays in RetryDelayCalculator::RETRY_DELAYS_IN_SECONDS; attempt 6 is terminal.
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
        private readonly EndpointHealth $endpointHealth,
        private readonly ErrorClassifier $errorClassifier,
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

    /**
     * Dispatches held webhooks so WebhookTransport stores them as paused (held) rows; they are
     * never delivered here. The counterpart of {@see process()} for the dispatch gate's Hold
     * decision (#16565).
     *
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
                // Should be rare under StreamLease: it means the lease was lost or crash-recovery re-claimed the row.
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
        // These headers exist only for rework envelopes: legacy envelopes have no reliable
        // dispatch-order sequence, so we leave them out entirely.
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

        // Insert the row as RUNNING right away so the async receiver cannot claim it mid-flight.
        // A concurrent inline caller collides on the event_log insert and gets null here.
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
            // A stale success on a stolen lease must not reset error_count.
            if ($this->webhookOutboxStore->markSuccess($entry, $response)) {
                if (Feature::isActive('WEBHOOKS_REWORK')) {
                    // Flag-on: the health model owns recovery. A 2xx climbs one state
                    // (SUSPENDED → DEGRADED → HEALTHY) and updates the per-webhook error_count
                    // mirror; the legacy shared-counter reset must not fire as well.
                    $this->endpointHealth->recordSuccess($webhookId);
                } else {
                    $this->webhookHealthService->resetErrorCount($webhookId);
                }

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

        $this->handleFailure($webhookId, $entry, $response, $result->exception, $this->retryAfterHeader($result));
    }

    private function handleFailure(string $webhookId, OutboxEntry $entry, ?DeliveryResponse $response, ?\Throwable $exception = null, ?string $retryAfter = null): void
    {
        // Flag-on path: classify the failure, transition health (which holds the webhook's backlog
        // when the breaker trips), then place the in-flight row based on the resulting state. The
        // legacy shared-counter / disable path is skipped — under the flag, the health model owns
        // active and error_count.
        if (Feature::isActive('WEBHOOKS_REWORK')) {
            // Mirror the success path's lease guard. If this attempt's row was already reclaimed
            // (crash-recovery reset it, or a → SUSPENDED drop deleted it), this late result is no
            // longer ours. recordFailure must not degrade or suspend the endpoint for a stale
            // attempt — its terminal write below would only no-op anyway. The success side already
            // gates recordSuccess on markSuccess().
            if (!$this->webhookOutboxStore->ownsRunningAttempt($entry)) {
                $this->logLeaseLost($webhookId, $entry);

                return;
            }

            // The exception is passed because a pre-response failure (DNS/timeout/TLS) has status 0.
            $classification = $this->errorClassifier->classify($response->responseStatusCode ?? 0, $exception);
            $state = $this->endpointHealth->recordFailure($webhookId, $classification, $entry->executionCount);
            // Only a 429 carries an actionable Retry-After; every other failure mode uses the fixed backoff.
            $rateLimitRetryAfter = $classification === ErrorClassification::TransientRateLimit ? $retryAfter : null;
            $this->placeInFlightRow($webhookId, $entry, $response, $classification, $state, $rateLimitRetryAfter);

            return;
        }

        // Legacy (flag-off) path — byte-equivalent to trunk.
        if (!$this->persistFailureOutcome($entry, $response)) {
            $this->logLeaseLost($webhookId, $entry);

            return;
        }

        // error_count counts failed deliveries, not failed attempts — only bump after retries are exhausted.
        if ($entry->executionCount > self::MAX_RETRIES) {
            $this->webhookHealthService->recordLegacyFailure($webhookId, $this->failureStrategy);
        }
    }

    /**
     * Flag-on result side: place the in-flight row based on the failure classification and the
     * webhook's resulting health state. Every non-transient failure is terminal, for two reasons.
     * A payload error is the sender's fault, so it never consumes a trial — no cooldown is
     * advanced, and the next tick releases the next-oldest held row. An auth/410 row must not
     * retry, or each retry would feed the once-per-delivery streak again. On a DEGRADED or
     * SUSPENDED webhook, a transient failure re-holds the row for the next cooldown release —
     * both states hold. DISABLED fails the row; HEALTHY retries with the normal backoff.
     */
    private function placeInFlightRow(
        string $webhookId,
        OutboxEntry $entry,
        ?DeliveryResponse $response,
        ErrorClassification $classification,
        EndpointState $state,
        ?string $retryAfter = null
    ): void {
        // Non-transient failures (payload, auth, 410) are terminal per the ADR's classification
        // table. Retrying a 401 won't change the answer, and each retry would count toward the
        // once-per-delivery auth streak again.
        if (!$classification->isTransient()) {
            $this->webhookOutboxStore->markFailed($entry, $response);

            return;
        }

        if ($state === EndpointState::Degraded || $state === EndpointState::Suspended) {
            $this->webhookOutboxStore->markPaused($entry, $response);

            return;
        }

        if ($state === EndpointState::Disabled) {
            $this->webhookOutboxStore->markFailed($entry, $response);

            return;
        }

        // HEALTHY: retry with the normal backoff (honouring a 429's Retry-After), and fail for
        // good once the retry budget is used up. A lost lease here is a real mid-flight reclaim
        // and worth logging, unlike the no-ops above.
        if (!$this->persistFailureOutcome($entry, $response, $retryAfter)) {
            $this->logLeaseLost($webhookId, $entry);
        }
    }

    /**
     * Returns the raw `Retry-After` value (case-insensitive, first occurrence) from a delivery
     * response, or null. Parsing and clamping happen in the {@see RetryDelayCalculator} — it owns
     * retry timing and the clock.
     */
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
