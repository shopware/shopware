<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Acl\AclRoleLoader;
use Shopware\Core\Framework\Api\Acl\IdentityType;
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
        // app_integration is the app whose integration created the webhook through the API.
        // Two joins instead of one with OR, so that both can use an index.
        $sql = <<<'SQL'
            SELECT
                LOWER(HEX(w.id)) as webhookId,
                w.name as webhookName,
                w.event_name as eventName,
                w.url as webhookUrl,
                w.only_live_version as onlyLiveVersion,
                LOWER(HEX(COALESCE(app.id, app_integration.id))) AS appId,
                COALESCE(app.name, app_integration.name) AS appName,
                COALESCE(app.active, app_integration.active) AS appActive,
                COALESCE(app.source_type, app_integration.source_type) AS appSourceType,
                COALESCE(app.version, app_integration.version) AS appVersion,
                COALESCE(app.app_secret, app_integration.app_secret) AS appSecret,
                LOWER(HEX(w.owner_user_id)) as ownerUserId,
                LOWER(HEX(COALESCE(app.integration_id, w.owner_integration_id))) as ownerIntegrationId,
                w.acl_role_ids as aclRoleIds
            FROM webhook w
            LEFT JOIN app ON (app.id = w.app_id)
            LEFT JOIN app app_integration ON (w.app_id IS NULL AND app_integration.integration_id = w.owner_integration_id)
            WHERE w.active = 1
              AND COALESCE(app.integration_id, w.owner_integration_id, w.owner_user_id) IS NOT NULL
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql);
        $owners = $this->aclRoleLoader->getRoles(
            array_values(array_filter(array_column($rows, 'ownerUserId'))),
            array_values(array_filter(array_column($rows, 'ownerIntegrationId'))),
        );

        $webhooks = [];
        foreach ($rows as $row) {
            $roles = $row['ownerIntegrationId'] !== null
                ? $owners->rolesFor($row['ownerIntegrationId'], IdentityType::Integration)
                : $owners->rolesFor($row['ownerUserId'], IdentityType::User);
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
