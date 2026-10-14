<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Acl\AclRoleLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriberType;
use Shopware\Core\Framework\Webhook\Authorization\WebhookSubscription;
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
     * @return array<string, WebhookSubscription> keyed by webhook id, without webhooks that have neither an app nor an owner
     */
    public function getSubscriptions(array $webhookIds): array
    {
        if ($webhookIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    LOWER(HEX(`id`)) as webhookId,
                    LOWER(HEX(`app_id`)) as appId,
                    LOWER(HEX(`owner_user_id`)) as ownerUserId,
                    LOWER(HEX(`owner_integration_id`)) as ownerIntegrationId,
                    `event_name` as eventName,
                    `acl_role_ids` as aclRoleIds
                FROM `webhook`
                WHERE `id` IN (:webhookIds)
            SQL,
            ['webhookIds' => Uuid::fromHexToBytesList($webhookIds)],
            ['webhookIds' => ArrayParameterType::BINARY]
        );

        $subscriptions = [];
        foreach ($rows as $row) {
            $ownerType = match (true) {
                $row['appId'] !== null => SubscriberType::App,
                $row['ownerIntegrationId'] !== null => SubscriberType::Integration,
                $row['ownerUserId'] !== null => SubscriberType::User,
                default => null,
            };

            if ($ownerType === null) {
                continue;
            }

            $subscriptions[$row['webhookId']] = new WebhookSubscription(
                $row['webhookId'],
                $ownerType,
                $row['appId'] ?? $row['ownerIntegrationId'] ?? $row['ownerUserId'],
                $row['eventName'],
                $row['aclRoleIds'] !== null ? Json::decodeToList($row['aclRoleIds']) : null,
            );
        }

        return $subscriptions;
    }

    /**
     * @return list<Webhook>
     */
    public function getWebhooks(): array
    {
        $sql = <<<'SQL'
            SELECT
                LOWER(HEX(w.id)) as webhookId,
                w.name as webhookName,
                w.event_name as eventName,
                w.url as webhookUrl,
                w.only_live_version as onlyLiveVersion,
                LOWER(HEX(a.id)) AS appId,
                a.name AS appName,
                a.active AS appActive,
                a.source_type AS appSourceType,
                a.version AS appVersion,
                a.app_secret AS appSecret,
                LOWER(HEX(w.owner_user_id)) as ownerUserId,
                LOWER(HEX(COALESCE(a.integration_id, w.owner_integration_id))) as ownerIntegrationId,
                w.acl_role_ids as aclRoleIds
            FROM webhook w
            LEFT JOIN app a ON (a.id = w.app_id)
            WHERE w.active = 1
              AND COALESCE(a.integration_id, w.owner_integration_id, w.owner_user_id) IS NOT NULL
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql);
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

            $roles = $ownerRoles[$ownerId];
            if ($row['aclRoleIds'] !== null) {
                $roles = $roles->intersect(Json::decodeToList($row['aclRoleIds']));
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
            );
        }

        return $webhooks;
    }
}
