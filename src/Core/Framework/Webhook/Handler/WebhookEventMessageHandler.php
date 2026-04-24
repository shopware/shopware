<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Handler;

use GuzzleHttp\Exception\BadResponseException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Service\RelatedWebhooks;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\Service\WebhookResult;
use Shopware\Core\Framework\Webhook\WebhookException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
#[Package('framework')]
final readonly class WebhookEventMessageHandler
{
    /**
     * @internal
     */
    public function __construct(
        private WebhookClient $webhookClient,
        private RelatedWebhooks $relatedWebhooks,
        private OutboxEventRepository $outboxEventRepository,
        private WebhookDeliveryService $webhookDeliveryService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(WebhookEventMessage $message): void
    {
        // Legacy pre-transport messages (partitionKey === null) were serialized before the
        // transport existed, so they have no delivery row yet — create it silently. For new
        // messages a missing row means an unexpected dispatch path or a rollout window; repair
        // and log.
        // @deprecated tag:v6.8.0 — remove with the flag-OFF path.
        if (!$this->outboxEventRepository->hasDeliveryRow($message->getWebhookEventId())) {
            $insert = OutboxInsert::fromMessage($message);
            if ($this->outboxEventRepository->ensureOutboxEntry($insert) === null) {
                $this->outboxEventRepository->backfillDelivery($insert);
            }

            if ($message->partitionKey !== null) {
                $this->logger->error('Expected an outbox entry for webhook event. Not an error if this is happening during a deployment rollout.', [
                    'webhookEventId' => $message->getWebhookEventId(),
                    'webhookId' => $message->getWebhookId(),
                ]);
            }
        }

        if (Feature::isActive('WEBHOOKS_REWORK')) {
            $this->webhookDeliveryService->deliver($message);

            return;
        }

        $context = Context::createDefaultContext();

        $entry = $this->outboxEventRepository->markRunning($message->getWebhookEventId());
        if ($entry === null) {
            $status = $this->outboxEventRepository->loadEventLogStatus($message->getWebhookEventId());
            if (\in_array($status, [
                WebhookEventLogDefinition::STATUS_SUCCESS,
                WebhookEventLogDefinition::STATUS_FAILED,
            ], true)) {
                return;
            }
        }

        // Only legacy pre-transport messages have no partitionKey. A new-shape message with
        // no delivery row is already finalized; a new-shape message with an active delivery row
        // is still owned elsewhere and must not be ACKed under the flag-OFF Messenger retry path.
        if ($entry === null && $message->partitionKey !== null) {
            if ($this->outboxEventRepository->hasDeliveryRow($message->getWebhookEventId())) {
                throw WebhookException::webhookFailedException(
                    $message->getWebhookId(),
                    new \RuntimeException('Webhook delivery is already running.')
                );
            }

            if (\in_array($status, [
                WebhookEventLogDefinition::STATUS_QUEUED,
                WebhookEventLogDefinition::STATUS_RUNNING,
                WebhookEventLogDefinition::STATUS_PENDING_RETRY,
            ], true)) {
                throw WebhookException::webhookFailedException(
                    $message->getWebhookId(),
                    new \RuntimeException('Webhook delivery is not terminal but has no delivery row.')
                );
            }

            return;
        }

        $request = $this->webhookDeliveryService->buildRequest($message, $entry);

        try {
            $result = $this->webhookClient->send($request);
        } catch (\Throwable $e) {
            $response = DeliveryResponse::from($request, new WebhookResult(
                body: [],
                statusCode: null,
                reasonPhrase: null,
                headers: null,
                errorMessage: $e->getMessage(),
                exception: $e,
            ));

            $this->outboxEventRepository->resetForRetry(
                $message->getWebhookEventId(),
                $response,
                $entry?->executionCount,
                $entry?->sequence,
            );

            throw WebhookException::webhookFailedException($message->getWebhookId(), $e);
        }

        try {
            $response = DeliveryResponse::from($request, $result);
        } catch (\JsonException $e) {
            $this->logger->error('Webhook delivery response serialization failed for event {eventId}', [
                'eventId' => $message->getWebhookEventId(),
                'webhookId' => $message->getWebhookId(),
                'exception' => $e,
            ]);

            if ($result->successful()) {
                $successRecorded = $this->outboxEventRepository->markSuccess(
                    $message->getWebhookEventId(),
                    null,
                    $entry?->executionCount,
                    $entry?->sequence,
                );

                if ($successRecorded) {
                    try {
                        $this->relatedWebhooks->updateRelated($message->getWebhookId(), ['error_count' => 0], $context);
                    } catch (AppNotFoundException|WriteTypeIntendException) {
                    }
                }

                return;
            }

            $this->outboxEventRepository->resetForRetry(
                $message->getWebhookEventId(),
                null,
                $entry?->executionCount,
                $entry?->sequence,
            );

            throw WebhookException::webhookFailedException($message->getWebhookId(), $e);
        }

        if ($result->successful()) {
            $successRecorded = $this->outboxEventRepository->markSuccess(
                $message->getWebhookEventId(),
                $response,
                $entry?->executionCount,
                $entry?->sequence,
            );

            if ($successRecorded) {
                try {
                    $this->relatedWebhooks->updateRelated($message->getWebhookId(), ['error_count' => 0], $context);
                } catch (AppNotFoundException|WriteTypeIntendException) {
                }
            }

            return;
        }

        $this->outboxEventRepository->resetForRetry(
            $message->getWebhookEventId(),
            $response,
            $entry?->executionCount,
            $entry?->sequence,
        );

        $exception = $result->exception;
        if ($exception instanceof BadResponseException && $message->getAppId() !== null) {
            throw WebhookException::appWebhookFailedException($message->getWebhookId(), $message->getAppId(), $exception);
        }

        throw WebhookException::webhookFailedException($message->getWebhookId(), $exception);
    }
}
