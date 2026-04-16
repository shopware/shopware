<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Handler;

use GuzzleHttp\Exception\BadResponseException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
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
        private AppPayloadServiceHelper $appPayloadServiceHelper,
        private RelatedWebhooks $relatedWebhooks,
        private OutboxEventRepository $outboxEventRepository,
        private WebhookDeliveryService $webhookDeliveryService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(WebhookEventMessage $message): void
    {
        // New messages (with partitionKey) must have a webhook_delivery row.
        // Legacy messages (without partitionKey, from before the webhook transport) are allowed to proceed without one.
        if ($message->hasOutboxEntry() && !$this->outboxEventRepository->hasDeliveryRow($message->getWebhookEventId())) {
            $this->logger->error('Webhook delivery aborted: outbox entry missing for event {eventId}. The webhook_delivery row should have been created by the transport sender.', [
                'eventId' => $message->getWebhookEventId(),
                'webhookId' => $message->getWebhookId(),
            ]);

            return;
        }

        if (Feature::isActive('WEBHOOKS_REWORK')) {
            $this->webhookDeliveryService->deliver($message);

            return;
        }

        $context = Context::createDefaultContext();

        $request = $this->appPayloadServiceHelper->createWebhookRequest(
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

        $this->outboxEventRepository->markRunning($message->getWebhookEventId());

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
