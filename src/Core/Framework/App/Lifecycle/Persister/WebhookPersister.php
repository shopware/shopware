<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Flow\Action\Action;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Webhook\Webhook;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\WebhookCacheClearer;

/**
 * @codeCoverageIgnore @see \Shopware\Tests\Integration\Core\Framework\App\Lifecycle\WebhookPersisterTest
 *
 * @internal only for use by the app-system
 *
 * @phpstan-type WebhookRecord array{name: string, event_name: string, url: string, only_live_version: int, app_id: string, active: int, error_count: int}
 */
#[Package('framework')]
class WebhookPersister implements PersisterInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly WebhookCacheClearer $cacheClearer,
    ) {
    }

    public function persist(AppLifecycleContext $context): void
    {
        $appId = $context->app->getId();
        $flowActions = $this->getFlowActions($context->appFilesystem);
        $webhooks = $this->getWebhooks($context->manifest, $flowActions, $appId, $context->defaultLocale, $context->hasAppSecret());

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

        $this->deleteOldWebhooks($existingWebhooks);
        $this->cacheClearer->clearWebhookCache();
    }

    /**
     * @param array<string, string> $toBeRemoved
     */
    private function deleteOldWebhooks(array $toBeRemoved): void
    {
        $this->connection->executeStatement(
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
            'error_count' => \array_key_exists('errorCount', $webhook) ? $webhook['errorCount'] : 0,
            'active' => 1,
            'app_id' => Uuid::fromHexToBytes($appId),
        ];
    }

    private function getFlowActions(Filesystem $fs): ?Action
    {
        if (!$fs->has('Resources/flow.xml')) {
            return null;
        }

        return Action::createFromXmlFile($fs->path('Resources/flow.xml'));
    }

    /**
     * @return array<array{name: string, eventName: string, url: string, onlyLiveVersion?: bool, errorCount?: int}>
     */
    private function getWebhooks(Manifest $manifest, ?Action $flowActions, string $appId, string $defaultLocale, bool $hasAppSecret): array
    {
        $actions = [];

        if ($flowActions) {
            $actions = $flowActions->getActions()?->getActions() ?? [];
        }

        $webhooks = array_map(function ($action) use ($appId) {
            $name = $action->getMeta()->getName();

            return [
                'name' => $name,
                'eventName' => $name,
                'url' => $action->getMeta()->getUrl(),
                'appId' => $appId,
                'active' => true,
                'errorCount' => 0,
            ];
        }, $actions);

        if (!$hasAppSecret) {
            return $webhooks;
        }

        $manifestWebhooks = $manifest->getWebhooks()?->getWebhooks() ?? [];

        return array_merge($webhooks, array_map(function (Webhook $webhook) use ($defaultLocale, $appId) {
            /** @var array{name: string, event: string, url: string} $payload */
            $payload = $webhook->toArray($defaultLocale);
            $payload['appId'] = $appId;
            $payload['eventName'] = $webhook->getEvent();

            return $payload;
        }, $manifestWebhooks));
    }
}
