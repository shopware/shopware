<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Handler;

use GuzzleHttp\Exception\BadResponseException;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Service\RelatedWebhooks;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
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
        private readonly WebhookClient $webhookClient,
        private readonly AppPayloadServiceHelper $appPayloadServiceHelper,
        private readonly ClockInterface $clock,
        private readonly RelatedWebhooks $relatedWebhooks,
        private readonly OutboxEventRepository $outboxEventRepository,
    ) {
    }

    public function __invoke(WebhookEventMessage $message): void
    {
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
            $response = new DeliveryResponse(
                processingTime: $this->clock->now()->getTimestamp() - $request->timestamp,
                requestContent: json_encode(['headers' => $request->headers, 'body' => $request->body], \JSON_THROW_ON_ERROR),
            );

            $this->outboxEventRepository->resetForRetry($message->getWebhookEventId(), $response);

            throw WebhookException::webhookFailedException($message->getWebhookId(), $e);
        }

        $processingTime = $this->clock->now()->getTimestamp() - $request->timestamp;
        $response = DeliveryResponse::from($request, $result, $processingTime);

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
