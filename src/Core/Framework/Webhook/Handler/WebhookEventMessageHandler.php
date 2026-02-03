<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Handler;

use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Exception\WebhookSendException;
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
        private EntityRepository $webhookEventLogRepository,
        private RelatedWebhooks $relatedWebhooks,
    ) {
    }

    public function __invoke(WebhookEventMessage $message): void
    {
        $context = Context::createDefaultContext();
        $timestamp = time();

        $this->updateLogIfItExists(
            [
                'id' => $message->getWebhookEventId(),
                'deliveryStatus' => WebhookEventLogDefinition::STATUS_RUNNING,
                'timestamp' => $timestamp,
            ],
            $context
        );

        try {
            $response = $this->webhookClient->send($message);

            $this->updateLogIfItExists(
                [
                    'id' => $message->getWebhookEventId(),
                    'deliveryStatus' => WebhookEventLogDefinition::STATUS_SUCCESS,
                    'processingTime' => time() - $timestamp,
                    'responseContent' => [
                        'headers' => $response['headers'],
                        'body' => $response['body'],
                    ],
                    'responseStatusCode' => $response['statusCode'],
                    'responseReasonPhrase' => $response['reasonPhrase'],
                ],
                $context
            );

            try {
                $this->relatedWebhooks->updateRelated($message->getWebhookId(), ['error_count' => 0], $context);
            } catch (AppNotFoundException|WriteTypeIntendException) {
                // may happen if app or webhook got deleted in the meantime,
                // we don't need to update the error-count in that case, so we can ignore the error
            }
        } catch (WebhookSendException $e) {
            $payload = [
                'id' => $message->getWebhookEventId(),
                'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED, // we use the message retry mechanism to retry the message here so we set the status to queued, because it will be automatically executed again.
                'processingTime' => time() - $timestamp,
            ];

            if ($e->hasResponse()) {
                $payload = array_merge($payload, [
                    'responseContent' => [
                        'headers' => $e->getResponseHeaders(),
                        'body' => $e->getResponseBody(),
                    ],
                    'responseStatusCode' => $e->getResponseStatusCode(),
                    'responseReasonPhrase' => $e->getResponseReasonPhrase(),
                ]);
            }

            $this->updateLogIfItExists($payload, $context);

            if ($e->hasResponse() && $message->getAppId()) {
                throw WebhookException::appWebhookFailedException($message->getWebhookId(), $message->getAppId(), $e);
            }

            throw WebhookException::webhookFailedException($message->getWebhookId(), $e);
        }
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
