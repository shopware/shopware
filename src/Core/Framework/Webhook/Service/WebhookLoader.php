<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Api\Acl\AclRoleLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\Ownership;
use Shopware\Core\Framework\Webhook\Webhook;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Webhook\Service\WebhookLoaderTest
 */
#[Package('framework')]
class WebhookLoader
{
    public function __construct(
        private readonly Connection $connection,
        private readonly AclRoleLoader $aclRoleLoader,
    ) {
    }

    /**
     * @param list<string> $webhookIds
     *
     * @return list<Ownership>
     */
    public function getOwnership(array $webhookIds): array
    {
        if ($webhookIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    LOWER(HEX(`id`)) as webhookId,
                    LOWER(HEX(`app_id`)) as appId,
                    LOWER(HEX(COALESCE(`owner_integration_id`, `owner_user_id`))) as ownerId
                FROM `webhook`
                WHERE `id` IN (:webhookIds)
            SQL,
            ['webhookIds' => Uuid::fromHexToBytesList($webhookIds)],
            ['webhookIds' => ArrayParameterType::BINARY]
        );

        return array_map(
            static fn (array $row) => new Ownership(
                $row['webhookId'],
                $row['appId'],
                $row['ownerId'],
            ),
            $rows
        );
    }

    /**
     * @return list<Webhook>
     */
    public function getWebhooks(): array
    {
        $query = $this->createWebhookQuery()->andWhere('w.active = 1');

        return $this->hydrateWebhooks($query->executeQuery()->fetchAllAssociative());
    }

    /**
     * @param list<string> $webhookIds
     *
     * @return list<Webhook>
     */
    public function getWebhooksByIds(array $webhookIds): array
    {
        if ($webhookIds === []) {
            return [];
        }

        $query = $this->createWebhookQuery()
            ->andWhere('w.id IN (:webhookIds)')
            ->setParameter('webhookIds', Uuid::fromHexToBytesList($webhookIds), ArrayParameterType::BINARY);

        return $this->hydrateWebhooks($query->executeQuery()->fetchAllAssociative());
    }

    private function createWebhookQuery(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select(
                'LOWER(HEX(w.id)) as webhookId',
                'w.name as webhookName',
                'w.event_name as eventName',
                'w.url as webhookUrl',
                'w.only_live_version as onlyLiveVersion',
                'LOWER(HEX(a.id)) AS appId',
                'a.name AS appName',
                'a.active AS appActive',
                'a.source_type AS appSourceType',
                'a.version AS appVersion',
                'a.app_secret AS appSecret',
                'LOWER(HEX(w.owner_user_id)) as ownerUserId',
                'LOWER(HEX(COALESCE(a.integration_id, w.owner_integration_id))) as ownerIntegrationId',
                'w.acl_role_ids as aclRoleIds',
            )
            ->from('webhook', 'w')
            ->leftJoin('w', 'app', 'a', 'a.id = w.app_id')
            ->where('COALESCE(a.integration_id, w.owner_integration_id, w.owner_user_id) IS NOT NULL');
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<Webhook>
     */
    private function hydrateWebhooks(array $rows): array
    {
        $ownerRoles = $this->aclRoleLoader->getRoles(array_values(array_filter([
            ...array_column($rows, 'ownerUserId'),
            ...array_column($rows, 'ownerIntegrationId'),
        ])));

        $webhooks = [];
        foreach ($rows as $row) {
            $ownerId = $row['ownerIntegrationId'] ?? $row['ownerUserId'];
            if (!isset($ownerRoles[$ownerId])) {
                continue;
            }

            $aclRoleIds = $row['aclRoleIds'] !== null ? Json::decodeToList($row['aclRoleIds']) : null;
            $roles = $ownerRoles[$ownerId];
            if ($aclRoleIds !== null) {
                $roles = $roles->intersect($aclRoleIds);
            }

            $webhooks[] = new Webhook(
                $row['webhookId'],
                $row['webhookName'],
                $row['eventName'],
                $row['webhookUrl'],
                (bool) $row['onlyLiveVersion'],
                $row['appId'],
                $row['appName'],
                $row['appSourceType'],
                (bool) $row['appActive'],
                $row['appVersion'],
                $row['appSecret'],
                roles: $roles,
                aclRoleIds: $aclRoleIds,
            );
        }

        return $webhooks;
    }
}
