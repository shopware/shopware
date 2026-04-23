<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Handler;

use GuzzleHttp\Exception\BadResponseException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
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
        // Only legacy pre-transport messages have no partitionKey. For any new-shape message,
        // `markRunning === null` means the row was either claimed by another worker or already
        // finalized, thus we don't need to do anything here.
        if ($entry === null && $message->partitionKey !== null) {
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

            $this->outboxEventRepository->resetForRetry($message->getWebhookEventId(), $response);

            throw WebhookException::webhookFailedException($message->getWebhookId(), $e);
        }

        $response = DeliveryResponse::from($request, $result);

        if ($result->successful()) {
            $this->outboxEventRepository->markSuccess($message->getWebhookEventId(), $response);

            try {
                $this->relatedWebhooks->updateRelated($message->getWebhookId(), ['error_count' => 0], $context);
            } catch (AppNotFoundException|WriteTypeIntendException) {
            }

            return;
        }

        $this->outboxEventRepository->resetForRetry($message->getWebhookEventId(), $response);

        $exception = $result->exception;
        if ($exception instanceof BadResponseException && $message->getAppId() !== null) {
            throw WebhookException::appWebhookFailedException($message->getWebhookId(), $message->getAppId(), $exception);
        }

        throw WebhookException::webhookFailedException($message->getWebhookId(), $exception);
    }
}
