<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Acl\AclRoleLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
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
            LEFT JOIN app owner_app ON (owner_app.integration_id = w.owner_integration_id)
            WHERE w.active = 1
              AND COALESCE(a.integration_id, w.owner_integration_id, w.owner_user_id) IS NOT NULL
              AND (owner_app.id IS NULL OR owner_app.active = 1)
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql);
        $userRoles = $this->aclRoleLoader->getUserRoles(array_values(array_filter(array_column($rows, 'ownerUserId'))));
        $integrationRoles = $this->aclRoleLoader->getIntegrationRoles(array_values(array_filter(array_column($rows, 'ownerIntegrationId'))));

        $webhooks = [];
        foreach ($rows as $row) {
            $roles = $row['ownerIntegrationId'] !== null
                ? $integrationRoles[$row['ownerIntegrationId']] ?? null
                : $userRoles[$row['ownerUserId']] ?? null;
            if ($roles === null) {
                continue;
            }

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
