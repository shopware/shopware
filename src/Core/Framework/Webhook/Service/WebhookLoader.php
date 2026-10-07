<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\Ownership;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\OwnerType;
use Shopware\Core\Framework\Webhook\Webhook;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @phpstan-type Owner array{type: OwnerType, roleIds: list<string>}
 * @phpstan-type OwnerRoleRow array{id: string, admin: bool|int|string, roleId: string|null}
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Webhook\Service\WebhookLoaderTest
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
                    LOWER(HEX(`owner_user_id`)) as ownerUserId,
                    LOWER(HEX(`owner_integration_id`)) as ownerIntegrationId
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
                $row['ownerUserId'],
                $row['ownerIntegrationId'],
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
        $ownerRoles = $this->resolveOwnerRoles($rows);

        return array_map(
            static function (array $row) use ($ownerRoles): Webhook {
                $aclRoleIds = self::decodeAclRoleIds($row['aclRoleIds']);
                $owner = self::narrowOwner($ownerRoles[$row['webhookId']], $aclRoleIds);

                return new Webhook(
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
                    ownerType: $owner['type'],
                    ownerRoleIds: $owner['roleIds'],
                    aclRoleIds: $aclRoleIds,
                );
            },
            $rows
        );
    }

    /**
     * @return list<string>|null
     */
    private static function decodeAclRoleIds(mixed $aclRoleIds): ?array
    {
        if (!\is_string($aclRoleIds)) {
            return null;
        }

        return array_values(array_filter(Json::decodeToList($aclRoleIds)));
    }

    /**
     * Applies the webhook's aclRoleIds to its owner's roles.
     *
     * If a user holds roles A and B and the webhook lists A and C, the webhook gets A. It never gets
     * a role its owner does not hold. An admin holds every role, so an admin's webhook gets exactly
     * the roles it lists. Without aclRoleIds, the webhook gets all of its owner's roles.
     *
     * @param Owner $owner
     * @param list<string>|null $aclRoleIds
     *
     * @return Owner
     */
    private static function narrowOwner(array $owner, ?array $aclRoleIds): array
    {
        if ($aclRoleIds === null) {
            return $owner;
        }

        if ($owner['type'] === OwnerType::Admin) {
            return ['type' => OwnerType::Restricted, 'roleIds' => $aclRoleIds];
        }

        return ['type' => OwnerType::Restricted, 'roleIds' => array_values(array_intersect($owner['roleIds'], $aclRoleIds))];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, Owner> keyed by webhook id
     */
    private function resolveOwnerRoles(array $rows): array
    {
        $userIds = array_values(array_filter(array_column($rows, 'ownerUserId')));
        $integrationIds = array_values(array_filter(array_column($rows, 'ownerIntegrationId')));

        $owners = $this->groupRolesByOwner([
            ...$this->loadUserRoleRows($userIds),
            ...$this->loadIntegrationRoleRows($integrationIds),
        ]);

        $webhookOwnerRoles = [];
        foreach ($rows as $row) {
            $webhookOwnerRoles[$row['webhookId']] = $owners[$row['ownerIntegrationId'] ?? $row['ownerUserId']];
        }

        return $webhookOwnerRoles;
    }

    /**
     * @param list<string> $userIds
     *
     * @return list<OwnerRoleRow>
     */
    private function loadUserRoleRows(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        /** @var list<OwnerRoleRow> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(u.id)) as id, u.admin as admin, LOWER(HEX(ur.acl_role_id)) as roleId
             FROM user u
             LEFT JOIN acl_user_role ur ON ur.user_id = u.id
             WHERE u.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($userIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $rows;
    }

    /**
     * @param list<string> $integrationIds
     *
     * @return list<OwnerRoleRow>
     */
    private function loadIntegrationRoleRows(array $integrationIds): array
    {
        if ($integrationIds === []) {
            return [];
        }

        /** @var list<OwnerRoleRow> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(i.id)) as id, i.admin as admin, LOWER(HEX(r.acl_role_id)) as roleId
             FROM integration i
             LEFT JOIN (
                 SELECT integration_id, acl_role_id FROM integration_role
                 UNION
                 SELECT integration_id, acl_role_id FROM app WHERE acl_role_id IS NOT NULL
             ) r ON r.integration_id = i.id
             WHERE i.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($integrationIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $rows;
    }

    /**
     * @param list<OwnerRoleRow> $roleRows
     *
     * @return array<string, Owner>
     */
    private function groupRolesByOwner(array $roleRows): array
    {
        $owners = [];
        foreach ($roleRows as $row) {
            $id = $row['id'];

            $owners[$id] ??= [
                'type' => (bool) $row['admin'] ? OwnerType::Admin : OwnerType::Restricted,
                'roleIds' => [],
            ];

            if ($row['roleId'] !== null) {
                $owners[$id]['roleIds'][] = $row['roleId'];
            }
        }

        return $owners;
    }
}
