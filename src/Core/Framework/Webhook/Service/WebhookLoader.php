<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;

/**
 * @internal
 *
 * @codeCoverageIgnore @see \Shopware\Tests\Integration\Core\Framework\Webhook\Service\WebhookLoaderTest
 *
 * @phpstan-type Webhook array{
 *     webhookId: string,
 *     webhookName: string,
 *     eventName: string,
 *     webhookUrl: string,
 *     onlyLiveVersion: bool,
 *     appId: string|null,
 *     appName: string|null,
 *     appActive: bool,
 *     appVersion: string|null,
 *     appSecret: string|null,
 *     appAclRoleId: string|null
 * }
 */
#[Package('core')]
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
                LOWER(HEX(MIN(w.id))) as webhookId,
                MIN(w.name) as webhookName,
                w.event_name as eventName,
                w.url as webhookUrl,
                w.only_live_version as onlyLiveVersion,
                LOWER(HEX(MIN(a.id))) AS appId,
                MIN(a.name) AS appName,
                MIN(a.active) AS appActive,
                MIN(a.version) AS appVersion,
                MIN(a.app_secret) AS appSecret,
                LOWER(HEX(MIN(a.acl_role_id))) as appAclRoleId

            FROM webhook w
            LEFT JOIN app a ON (a.id = w.app_id)
            WHERE w.active = 1
            GROUP BY event_name, url, only_live_version
        SQL;

        $webhooks = $this->connection->fetchAllAssociative($sql);

        foreach ($webhooks as $k => $webhook) {
            $webhooks[$k]['appActive'] = (bool) $webhook['appActive'];
            $webhooks[$k]['onlyLiveVersion'] = (bool) $webhook['onlyLiveVersion'];
        }
        /** @var list<Webhook> $webhooks */

        return $webhooks;
    }
}
