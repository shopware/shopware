<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\PartitionAwareHookable;
use Shopware\Core\Framework\Webhook\Webhook;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
use Shopware\Core\Framework\Util\Hasher;

#[Package('framework')]
class WebhookOutboxWriter implements ResetInterface
{
    /**
     * @var array<string, bool>
     */
    private array $seenKeys = [];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function write(Webhook $webhook, WebhookEventMessage $message, ?Hookable $event = null): void
    {
        $partitionKey = $this->calculatePartitionKey($event, $webhook);

        $this->connection->insert(
            'webhook_event_log',
            [
                'id' => Uuid::fromHexToBytes($message->getWebhookEventId()),
                'app_name' => $webhook->appName,
                'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
                'webhook_name' => $webhook->webhookName,
                'event_name' => $webhook->eventName,
                'app_version' => $webhook->appVersion,
                'url' => $webhook->url,
                'only_live_version' => (int) $webhook->onlyLiveVersion,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'serialized_webhook_message' => serialize($message),
                'execution_count' => 0,
                'partition_key' => $partitionKey,
            ]
        );

        if (isset($this->seenKeys[$partitionKey])) {
            return;
        }

        $this->connection->executeStatement(
            'INSERT IGNORE INTO `webhook_stream` (`partition_key`, `created_at`, `error_count`) VALUES (:key, :now, 0)',
            [
                'key' => $partitionKey,
                'now' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->seenKeys[$partitionKey] = true;
    }

    public function reset(): void
    {
        $this->seenKeys = [];
    }

    private function calculatePartitionKey(?Hookable $event, Webhook $webhook): string
    {
        $appName = $webhook->appName;

        $eventPartitionKey = $event instanceof PartitionAwareHookable
            ? $event->getPartitionKey()
            : null;

        // Default: partition by app_name + "default" for app isolation by default
        // If event provides key: partition by app_name + event_key for ordering within app
        // We use a null byte as separator to avoid collisions if appName/eventKey contains the separator char
        $raw = sprintf("%s\0%s", $appName, $eventPartitionKey ?? 'default');

        // Why BINARY(8)?
        // 1. Storage Efficiency: Fixed 8 bytes vs variable string. Prevents index bloat.
        // 2. Performance: Numeric byte comparison avoids charset/collation overhead.
        // 3. Stability: Avoids key-length limits (3072 bytes).
        // 4. Algorithm: xxh3 (64-bit) ensures optimal distribution.
        return substr(Hasher::hashBinary($raw, 'xxh3'), 0, 8);
    }
}
