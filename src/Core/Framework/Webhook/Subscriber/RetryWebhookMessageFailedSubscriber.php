<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Service\RelatedWebhooks;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * @internal
 */
#[Package('framework')]
class RetryWebhookMessageFailedSubscriber implements EventSubscriberInterface
{
    private const MAX_WEBHOOK_ERROR_COUNT = 10;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly RelatedWebhooks $relatedWebhooks
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'failed',
        ];
    }

    public function failed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof WebhookEventMessage) {
            return;
        }

        $webhookId = $message->getWebhookId();
        $webhookEventLogId = $message->getWebhookEventId();

        $context = Context::createDefaultContext();

        $this->connection->executeStatement('UPDATE webhook_event_log SET delivery_status = :status WHERE id = :id', [
            'status' => WebhookEventLogDefinition::STATUS_FAILED,
            'id' => Uuid::fromHexToBytes($webhookEventLogId),
        ]);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT active, error_count FROM webhook WHERE id = :id',
            ['id' => $webhookId]
        );

        /** @var array{active: int, error_count: int} $webhook */
        $webhook = current($rows);

        if (!\is_array($webhook) || !$webhook['active']) {
            return;
        }

        $webhookErrorCount = $webhook['error_count'] + 1;
        $params = ['error_count' => $webhookErrorCount];

        if ($webhookErrorCount >= self::MAX_WEBHOOK_ERROR_COUNT) {
            $params = array_merge($params, [
                'error_count' => 0,
                'active' => false,
            ]);
        }

        $this->relatedWebhooks->updateRelated($webhookId, $params, $context);
    }
}
