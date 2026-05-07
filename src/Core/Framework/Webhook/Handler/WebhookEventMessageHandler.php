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
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\RelatedWebhooks;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
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
        private WebhookOutboxStore $webhookOutboxStore,
        private WebhookDeliveryService $webhookDeliveryService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(WebhookEventMessage $message): void
    {
        // Legacy pre-transport messages were serialized before the explicit partition key
        // transport existed, so they have no delivery row yet — create it silently. For new
        // messages a missing row means an unexpected dispatch path or a rollout window; repair
        // and log.
        // @deprecated tag:v6.8.0 — remove with the flag-OFF path.
        if (!$this->webhookOutboxStore->hasDeliveryRow($message->getWebhookEventId())) {
            $insert = OutboxInsert::fromMessage($message);
            // recordOutboxEntry handles the case where the app was deleted (`testCanStillSendAfterWebhookIsDeleted`) case.
            // backFillDelivery handles the legacy message case.
            if ($this->webhookOutboxStore->recordOutboxEntry($insert) === null) {
                $this->webhookOutboxStore->backfillDelivery($insert);
            }

            if ($message->isReworkEnvelope()) {
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

        $entry = $this->webhookOutboxStore->markRunning($message->getWebhookEventId());
        // Already transitioned, could be due to worker contention. Skip this delivery.
        if ($entry === null) {
            return;
        }

        $request = $this->webhookDeliveryService->buildRequest($message, $entry);
        $result = $this->webhookClient->send($request);
        $response = DeliveryResponse::from($request, $result);

        if ($result->successful()) {
            // a stale-success on a stolen lease must not reset error_count.
            if ($this->webhookOutboxStore->markSuccess($entry, $response)) {
                try {
                    $this->relatedWebhooks->updateRelated($message->getWebhookId(), ['error_count' => 0], $context);
                } catch (AppNotFoundException|WriteTypeIntendException) {
                }
            }

            return;
        }

        $this->webhookOutboxStore->resetForRetry($entry, $response);

        $exception = $result->exception;
        if ($exception instanceof BadResponseException && $message->getAppId() !== null) {
            throw WebhookException::appWebhookFailedException($message->getWebhookId(), $message->getAppId(), $exception);
        }

        throw WebhookException::webhookFailedException($message->getWebhookId(), $exception);
    }
}
