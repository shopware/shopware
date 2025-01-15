<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Service\RelatedWebhooks;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * @internal
 */
#[Package('core')]
class RetryWebhookMessageFailedSubscriber implements EventSubscriberInterface
{
    private const MAX_WEBHOOK_ERROR_COUNT = 10;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $webhookEventLogRepository,
        private readonly RelatedWebhooks $relatedWebhooks
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'failed',
        ];
    }

    /**
     * Listen for WebhookEventMessage failures:
     * 1. Increase the error count of the webhook
     * 2. Mark the event log as failed
     * 3. Disable the webhook completely when the total number of errors equals @see self::MAX_WEBHOOK_ERROR_COUNT
     */
    public function failed(WorkerMessageFailedEvent $event): void
    {
        // if this message is already flagged as retry (possibly by a custom retry strategy
        // we ignore it here.
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

        $this->markWebhookEventFailed($webhookEventLogId, $context);

        $webhook = $this->fetchWebhookIfActive($webhookId);

        if (!$webhook) {
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

    /**
     * @return array{active: int, error_count: int}|null
     */
    private function fetchWebhookIfActive(string $webhookId): ?array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT active, error_count FROM webhook WHERE id = :id',
            ['id' => $webhookId]
        );

        /** @var array{active: int, error_count: int}|false $webhook */
        $webhook = current($rows);

        if (!\is_array($webhook) || !$webhook['active']) {
            return null;
        }

        return $webhook;
    }

    private function markWebhookEventFailed(string $id, Context $context): void
    {
        $this->webhookEventLogRepository->update([
            ['id' => $id, 'deliveryStatus' => WebhookEventLogDefinition::STATUS_FAILED],
        ], $context);
    }
}
