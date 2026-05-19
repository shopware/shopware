<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Handler;

use GuzzleHttp\Exception\BadResponseException;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
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
     *
     * @param EntityRepository<ScheduledTaskCollection> $webhookEventLogRepository
     */
    public function __construct(
        private WebhookClient $webhookClient,
        private readonly AppPayloadServiceHelper $appPayloadServiceHelper,
        private readonly ClockInterface $clock,
        private EntityRepository $webhookEventLogRepository,
        private RelatedWebhooks $relatedWebhooks,
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

        $this->updateLogIfItExists(
            [
                'id' => $message->getWebhookEventId(),
                'deliveryStatus' => WebhookEventLogDefinition::STATUS_RUNNING,
                'timestamp' => $request->timestamp,
                'requestContent' => [
                    'headers' => $request->headers,
                    'body' => $request->body,
                ],
            ],
            $context
        );

        $result = $this->webhookClient->send($request);

        if ($result->successful()) {
            $this->updateLogIfItExists(
                [
                    'id' => $message->getWebhookEventId(),
                    'deliveryStatus' => WebhookEventLogDefinition::STATUS_SUCCESS,
                    'processingTime' => $this->clock->now()->getTimestamp() - $request->timestamp,
                    'responseContent' => [
                        'headers' => $result->headers,
                        'body' => $result->body,
                    ],
                    'responseStatusCode' => $result->statusCode,
                    'responseReasonPhrase' => $result->reasonPhrase,
                ],
                $context
            );

            try {
                $this->relatedWebhooks->updateRelated($message->getWebhookId(), ['error_count' => 0], $context);
            } catch (AppNotFoundException|WriteTypeIntendException) {
                // may happen if app or webhook got deleted in the meantime,
                // we don't need to update the error-count in that case, so we can ignore the error
            }

            return;
        }

        $payload = [
            'id' => $message->getWebhookEventId(),
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED, // we use the message retry mechanism to retry the message here so we set the status to queued, because it will be automatically executed again.
            'processingTime' => $this->clock->now()->getTimestamp() - $request->timestamp,
        ];

        if ($result->hasResponse()) {
            $payload = array_merge($payload, [
                'responseContent' => [
                    'headers' => $result->headers,
                    'body' => $result->body,
                ],
                'responseStatusCode' => $result->statusCode,
                'responseReasonPhrase' => $result->reasonPhrase,
            ]);
        }

        $this->updateLogIfItExists($payload, $context);

        $exception = $result->exception;
        if ($exception instanceof BadResponseException && $message->getAppId()) {
            throw WebhookException::appWebhookFailedException($message->getWebhookId(), $message->getAppId(), $exception);
        }

        throw WebhookException::webhookFailedException($message->getWebhookId(), $exception);
    }

    /**
     * @param array<string, mixed|null> $payload
     */
    private function updateLogIfItExists(array $payload, Context $context): void
    {
        try {
            $this->webhookEventLogRepository->update([$payload], $context);
        } catch (WriteTypeIntendException) {
            // ignore, as that indicates the log entry was already deleted, in that case we don't need to update it
        }
    }
}
