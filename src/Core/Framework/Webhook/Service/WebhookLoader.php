<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Webhook;

/**
 * @internal
 *
 * @codeCoverageIgnore @see \Shopware\Tests\Integration\Core\Framework\Webhook\Service\WebhookLoaderTest
 */
#[Package('framework')]
class WebhookLoader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<string> $roleIds
     *
     * @return array<string, AclPrivilegeCollection>
     */
    public function getPrivilegesForRoles(array $roleIds): array
    {
        $roles = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT `id`, `privileges`
                FROM `acl_role`
                WHERE `id` IN (:aclRoleIds)
            SQL,
            ['aclRoleIds' => Uuid::fromHexToBytesList($roleIds)],
            ['aclRoleIds' => ArrayParameterType::BINARY]
        );

        if (!$roles) {
            return [];
        }

        $privileges = [];
        foreach ($roles as $privilege) {
            $privileges[Uuid::fromBytesToHex($privilege['id'])]
                = new AclPrivilegeCollection(json_decode((string) $privilege['privileges'], true, 512, \JSON_THROW_ON_ERROR));
        }

        return $privileges;
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
                LOWER(HEX(a.acl_role_id)) as appAclRoleId
            FROM webhook w
            LEFT JOIN app a ON (a.id = w.app_id)
            WHERE w.active = 1
        SQL;

        $webhooks = $this->connection->fetchAllAssociative($sql);

        return array_map(
            fn (array $webhook) => new Webhook(
                $webhook['webhookId'],
                $webhook['webhookName'],
                $webhook['eventName'],
                $webhook['webhookUrl'],
                (bool) $webhook['onlyLiveVersion'],
                $webhook['appId'],
                $webhook['appName'],
                $webhook['appSourceType'],
                (bool) $webhook['appActive'],
                $webhook['appVersion'],
                $webhook['appSecret'],
                $webhook['appAclRoleId']
            ),
            $webhooks
        );
    }
}
