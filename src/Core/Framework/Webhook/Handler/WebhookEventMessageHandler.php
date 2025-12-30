<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Handler;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Service\RelatedWebhooks;
use Shopware\Core\Framework\Webhook\Service\WebhookSender;
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
        private WebhookSender $webhookSender,
        private EntityRepository $webhookEventLogRepository,
        private RelatedWebhooks $relatedWebhooks,
    ) {
    }

    public function __invoke(WebhookEventMessage $message): void
    {
        $webhookEventId = $message->getWebhookEventId();
        $requestContent = $this->webhookSender->buildRequestOptions($message);
        $timestamp = time();

        $this->updateLogIfItExists([
            'id' => $webhookEventId,
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_RUNNING,
            'timestamp' => $timestamp,
            'requestContent' => $requestContent,
        ], Context::createDefaultContext());

        try {
            $response = $this->webhookSender->send($message);

            $this->updateLogIfItExists([
                'id' => $webhookEventId,
                'deliveryStatus' => WebhookEventLogDefinition::STATUS_SUCCESS,
                'processingTime' => time() - $timestamp,
                'responseContent' => [
                    'headers' => $response->getHeaders(),
                    'body' => \json_decode($response->getBody()->getContents(), true),
                ],
                'responseStatusCode' => $response->getStatusCode(),
                'responseReasonPhrase' => $response->getReasonPhrase(),
            ], Context::createDefaultContext());

            try {
                $this->relatedWebhooks->updateRelated($message->getWebhookId(), ['error_count' => 0], Context::createDefaultContext());
            } catch (\Throwable) {
            }
        } catch (\Throwable $e) {
            $updates = [
                'id' => $webhookEventId,
                'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
                'processingTime' => time() - $timestamp,
            ];

            if ($e instanceof RequestException && $e->getResponse() !== null) {
                $response = $e->getResponse();
                $body = $response->getBody()->getContents();
                if (json_validate($body)) {
                    $body = \json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
                }
                $updates['responseContent'] = [
                    'headers' => $response->getHeaders(),
                    'body' => $body,
                ];
                $updates['responseStatusCode'] = $response->getStatusCode();
                $updates['responseReasonPhrase'] = $response->getReasonPhrase();
            }

            $this->updateLogIfItExists($updates, Context::createDefaultContext());

            if ($e instanceof BadResponseException && $message->getAppId()) {
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
        } catch (WriteTypeIntendException $e) {
            // ignore, as that indicates the log entry was already deleted, in that case we don't need to update it
        }
    }
}
