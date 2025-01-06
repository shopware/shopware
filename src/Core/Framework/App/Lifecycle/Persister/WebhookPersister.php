<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @codeCoverageIgnore @see \Shopware\Tests\Integration\Core\Framework\App\Lifecycle\WebhookPersisterTest
 *
 * @internal only for use by the app-system
 *
 * @phpstan-type WebhookRecord array{name: string, event_name: string, url: string, only_live_version: int, app_id: string, active: int, error_count: int}
 */
#[Package('core')]
class WebhookPersister
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<array{name: string, eventName: string, url: string, onlyLiveVersion?: bool, errorCount?: int}> $webhooks
     */
    public function updateWebhooksFromArray(array $webhooks, string $appId, Context $context): void
    {
        $existingWebhooks = $this->getExistingWebhooks($appId);
        $updates = [];
        $inserts = [];

        foreach ($webhooks as $webhook) {
            $payload = $this->toRecord($webhook, $appId);

            if ($id = array_search($webhook['name'], $existingWebhooks, true)) {
                unset($existingWebhooks[$id]);
                $updates[$id] = $payload;
                continue;
            }

            $payload['id'] = Uuid::randomBytes();
            $payload['created_at'] = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $inserts[] = $payload;
        }

        foreach ($updates as $id => $update) {
            $this->connection->update('webhook', $update, ['id' => Uuid::fromHexToBytes($id)]);
        }

        foreach ($inserts as $insert) {
            $this->connection->insert('webhook', $insert);
        }

        $this->deleteOldWebhooks($existingWebhooks, $context);
    }

    /**
     * @param array<string, string> $toBeRemoved
     */
    private function deleteOldWebhooks(array $toBeRemoved, Context $context): void
    {
        $this->connection->executeQuery(
            'DELETE FROM webhook WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList(array_keys($toBeRemoved))],
            ['ids' => ArrayParameterType::STRING],
        );
    }

    /**
     * @return array<string, string>
     */
    private function getExistingWebhooks(string $appId): array
    {
        $sql = <<<'SQL'
            SELECT
                LOWER(HEX(w.id)) as webhookId,
                w.name as webhookName
            FROM webhook w
            LEFT JOIN app a ON (a.id = w.app_id)
            WHERE LOWER(HEX(a.id)) = :appId

        SQL;

        /** @var array<string, string> $webhooks */
        $webhooks = $this->connection->fetchAllKeyValue(
            $sql,
            ['appId' => $appId]
        );

        return $webhooks;
    }

    /**
     * @param array{name: string, eventName: string, url: string, onlyLiveVersion?: bool, errorCount?: int} $webhook
     *
     * @return WebhookRecord
     */
    private function toRecord(array $webhook, string $appId): array
    {
        return [
            'name' => $webhook['name'],
            'event_name' => $webhook['eventName'],
            'url' => $webhook['url'],
            'only_live_version' => \array_key_exists('onlyLiveVersion', $webhook) ? (int) $webhook['onlyLiveVersion'] : 0,
            'error_count' => \array_key_exists('errorCount', $webhook) ? (int) $webhook['errorCount'] : 0,
            'active' => 1,
            'app_id' => Uuid::fromHexToBytes($appId),
        ];
    }
}
