<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Webhook\Subscriber\RetryWebhookMessageFailedSubscriberTest
 *
 * @internal
 */
#[Package('framework')]
class RetryWebhookMessageFailedSubscriber implements EventSubscriberInterface
{
    private readonly WebhookFailureStrategy $failureStrategy;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly WebhookOutboxStore $webhookOutboxStore,
        string $failureStrategy = WebhookFailureStrategy::DisableOnThreshold->value,
    ) {
        $this->failureStrategy = WebhookFailureStrategy::from($failureStrategy);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'failed',
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with WEBHOOKS_REWORK and webhook.active/error_count.
     *
     * @phpstan-ignore shopware.deprecatedMethod (called for every failed message; deprecation notices would pollute logs)
     */
    public function failed(WorkerMessageFailedEvent $event): void
    {
        if (Feature::isActive('WEBHOOKS_REWORK')) {
            return; // Handler owns retry lifecycle for all outbox-backed messages under the flag
        }

        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof WebhookEventMessage) {
            return;
        }

        if ($event->willRetry()) {
            return;
        }

        $markedFailed = $message->isReworkEnvelope()
            ? $this->webhookOutboxStore->markFailedAfterRetryExhaustedIfIdle($message->getWebhookEventId())
            : $this->webhookOutboxStore->markLegacyFailedAfterRetryExhausted($message->getWebhookEventId());

        if (!$markedFailed) {
            return;
        }

        $webhookId = $message->getWebhookId();

        $rows = $this->connection->fetchAllAssociative(
            'SELECT active, error_count FROM webhook WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );

        /** @var array{active: int, error_count: int} $webhook */
        $webhook = current($rows);

        if (!\is_array($webhook) || !$webhook['active']) {
            return;
        }

        $params = match ($this->failureStrategy) {
            WebhookFailureStrategy::DisableOnThreshold => $this->handleDisableOnThreshold($webhook),
            WebhookFailureStrategy::Ignore => $this->handleIgnore($webhook),
        };

        $this->connection->update('webhook', $params, ['id' => Uuid::fromHexToBytes($webhookId)]);
    }

    /**
     * @param array{active: int, error_count: int} $webhook
     *
     * @return array<string, int>
     */
    private function handleDisableOnThreshold(array $webhook): array
    {
        $errorCount = $webhook['error_count'] + 1;

        if ($errorCount >= WebhookFailureStrategy::MAX_ERROR_COUNT) {
            return ['error_count' => 0, 'active' => 0];
        }

        return ['error_count' => $errorCount];
    }

    /**
     * @param array{active: int, error_count: int} $webhook
     *
     * @return array<string, int>
     */
    private function handleIgnore(array $webhook): array
    {
        return ['error_count' => $webhook['error_count'] + 1];
    }
}
