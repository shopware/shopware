<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Privileges;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\App\Event\AppPermissionsUpdated;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore @see \Shopware\Tests\Integration\Core\Framework\App\Permission\PrivilegesTest
 */
#[Package('framework')]
class Privileges
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Accept the given list of permissions for an app
     *
     * @param list<string> $accept
     */
    public function acceptOnly(string $appId, array $accept, Context $context): void
    {
        [$existingPrivileges, $requestedPrivileges] = $this->fetchPrivileges([$appId])[$appId];

        $new = array_merge($existingPrivileges, array_intersect($accept, $requestedPrivileges));

        $remaining = array_diff($requestedPrivileges, $accept);

        $this->writePrivileges($appId, $new, $remaining, $context);
    }

    /**
     * Accept all requested permissions for the given list of apps
     *
     * @param list<string> $appIds
     */
    public function acceptAllForApps(array $appIds, Context $context): void
    {
        $privileges = $this->fetchPrivileges($appIds);

        foreach ($appIds as $appId) {
            [$existingPrivileges, $requestedPrivileges] = $privileges[$appId];

            $new = array_merge($existingPrivileges, $requestedPrivileges);

            $this->writePrivileges($appId, $new, [], $context);
        }
    }

    /**
     * @param list<string> $appIds
     */
    public function revokeAllForApps(array $appIds, Context $context): void
    {
        foreach ($appIds as $appId) {
            $this->writePrivileges($appId, [], [], $context);
        }
    }

    /**
     * Get the requested privileges for all active apps
     *
     * @return array<string, list<string>>
     */
    public function getRequestedPrivilegesForAllApps(): array
    {
        /** @var array<string, string> $privileges */
        $privileges = $this->connection->fetchAllKeyValue(
            <<<'SQL'
                SELECT name, requested_privileges
                FROM app
                WHERE app.active = 1
            SQL,
        );

        return $this->decodePrivileges($privileges);
    }

    /**
     * Get the current privileges for the given list of apps
     *
     * @param list<string> $appIds
     *
     * @return array<string, list<string>>
     */
    public function getPrivileges(array $appIds = []): array
    {
        return array_map(
            fn (array $privileges): array => $privileges[0],
            $this->fetchPrivileges($appIds)
        );
    }

    /**
     *  Get the requested privileges for the given list of apps
     *
     * @param list<string> $appIds
     *
     * @return array<string, list<string>>
     */
    public function getRequestedPrivileges(array $appIds = []): array
    {
        /** @var array<string, string> $privileges */
        $privileges = $this->connection->fetchAllKeyValue(
            <<<'SQL'
                SELECT LOWER(HEX(app.id)) AS app_id, requested_privileges
                FROM app
                WHERE id IN (:ids)
            SQL,
            ['ids' => Uuid::fromHexToBytesList($appIds)],
            ['ids' => ArrayParameterType::STRING]
        );

        return $this->decodePrivileges($privileges);
    }

    /**
     * @param list<string> $privileges
     */
    public function setPrivileges(string $appId, array $privileges, Context $context): void
    {
        $this->connection->executeStatement(
            'UPDATE `acl_role` SET `privileges` = :privileges WHERE id = (SELECT acl_role_id FROM app WHERE id = :id)',
            [
                'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR),
                'id' => Uuid::fromHexToBytes($appId),
            ]
        );

        $this->eventDispatcher->dispatch(new AppPermissionsUpdated($appId, $privileges, $context));
    }

    /**
     * @param list<string> $privileges
     */
    public function requestPrivileges(string $appId, array $privileges, Context $context): void
    {
        $existingPrivileges = $this->connection->fetchOne(
            'SELECT privileges FROM `acl_role` WHERE id = (SELECT acl_role_id FROM app WHERE id = :id)',
            ['id' => Uuid::fromHexToBytes($appId)]
        );

        $existingPrivileges = json_decode($existingPrivileges, true, \JSON_THROW_ON_ERROR);

        sort($privileges);
        sort($existingPrivileges);

        // nothing new here
        if ($existingPrivileges === $privileges) {
            return;
        }

        // existing privileges with newly removed privileges applied
        // we can instantly remove them
        $updatedPrivileges = array_intersect($existingPrivileges, $privileges);

        $new = array_values(array_diff($privileges, $updatedPrivileges));

        $this->writePrivileges($appId, $updatedPrivileges, $new, $context);
    }

    /**
     * @param array<string, string> $privileges
     *
     * @return array<string, list<string>>
     */
    private function decodePrivileges(array $privileges): array
    {
        return array_map(
            fn (?string $appPrivileges) => $appPrivileges
                ? json_decode($appPrivileges, true, \JSON_THROW_ON_ERROR)
                : [],
            $privileges
        );
    }

    /**
     * @param list<string> $appIds
     *
     * @return array<string, array{0: list<string>, 1: list<string>}>
     */
    private function fetchPrivileges(array $appIds): array
    {
        /** @var array<string, array{privileges: string, requested_privileges: string}> $privileges */
        $privileges = $this->connection->fetchAllAssociativeIndexed(
            <<<'SQL'
                SELECT LOWER(HEX(a.id)), privileges, requested_privileges
                FROM `acl_role` r
                INNER JOIN `app` a ON a.acl_role_id = r.id
                WHERE a.id IN (:appIds)
            SQL,
            ['appIds' => Uuid::fromHexToBytesList($appIds)],
            ['appIds' => ArrayParameterType::STRING]
        );

        return array_map(fn (array $row): array => [
            json_decode($row['privileges'], true, \JSON_THROW_ON_ERROR),
            json_decode($row['requested_privileges'], true, \JSON_THROW_ON_ERROR),
        ], $privileges);
    }

    /**
     * @param array<string> $privileges
     * @param array<string> $requestedPrivileges
     */
    private function writePrivileges(string $appId, array $privileges, array $requestedPrivileges, Context $context): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE `acl_role`
                SET `privileges` = :privileges
                WHERE id = (SELECT acl_role_id FROM app WHERE id = :id)
            SQL,
            [
                'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR),
                'id' => Uuid::fromHexToBytes($appId),
            ]
        );

        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE `app`
                SET `requested_privileges` = :requestedPrivileges
                WHERE id = :id
            SQL,
            [
                'requestedPrivileges' => json_encode($requestedPrivileges, \JSON_THROW_ON_ERROR),
                'id' => Uuid::fromHexToBytes($appId),
            ]
        );

        $this->eventDispatcher->dispatch(new AppPermissionsUpdated($appId, $privileges, $context));
    }
}
