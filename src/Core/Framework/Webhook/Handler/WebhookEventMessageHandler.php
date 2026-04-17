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
use Shopware\Core\Framework\Util\Hasher;
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
        private AppPayloadServiceHelper $appPayloadServiceHelper,
        private RelatedWebhooks $relatedWebhooks,
        private OutboxEventRepository $outboxEventRepository,
        private WebhookDeliveryService $webhookDeliveryService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(WebhookEventMessage $message): void
    {
        // Ensure a delivery row exists for this message. The transport sender normally
        // creates it before dispatch, but we repair the state here so every code path
        // reaches the same unified outbox flow.
        //
        // - Legacy pre-transport messages (partitionKey === null) legitimately have no
        //   delivery row yet — they were serialized before the transport existed. Creating
        //   the row now is expected, so we don't log anything.
        // - New-style messages (partitionKey !== null) should already have a delivery
        //   row by the time they land here. Missing one indicates a problem (e.g. a brief
        //   deployment rollout window, or an unexpected dispatch path). We still repair
        //   it, but log the discrepancy so it surfaces in monitoring.
        if (!$this->outboxEventRepository->hasDeliveryRow($message->getWebhookEventId())) {
            $this->outboxEventRepository->ensureOutboxEntry(new OutboxInsert(
                $message->getWebhookEventId(),
                $message->getWebhookId(),
                Hasher::hashBinary($message->getPartitionKey(), 'xxh128'),
                serialize($message),
            ));

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
