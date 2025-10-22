<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Privileges;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Event\AppPermissionsUpdated;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
     * Update the privileges for the given app
     *
     * @param list<string> $accept
     * @param list<string> $revoke
     *
     * @throws AppException
     */
    public function updatePrivileges(string $appId, array $accept, array $revoke, Context $context): void
    {
        if (\count($accept) === 0 && \count($revoke) === 0) {
            return;
        }

        if (\count(array_intersect($accept, $revoke)) !== 0) {
            throw AppException::conflictingPrivilegeUpdate();
        }

        [$existingPrivileges, $requestedPrivileges] = $this->fetchPrivileges([$appId])[$appId];

        $revokedActive = array_intersect($revoke, $existingPrivileges);
        $existingPrivileges = array_values(array_diff($existingPrivileges, $revoke));
        $requestedPrivileges = array_values(array_unique(array_merge($requestedPrivileges, $revokedActive)));

        $acceptedPrivileges = array_intersect($accept, $requestedPrivileges);
        $existingPrivileges = array_values(array_unique(array_merge($existingPrivileges, $acceptedPrivileges)));
        $requestedPrivileges = array_values(array_diff($requestedPrivileges, $accept));

        $this->writePrivileges($appId, $existingPrivileges, $requestedPrivileges, $context);
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
        $privileges = $this->fetchPrivileges($appIds);

        foreach ($appIds as $appId) {
            [$existingPrivileges, $requestedPrivileges] = $privileges[$appId];

            $new = array_merge($existingPrivileges, $requestedPrivileges);

            $this->writePrivileges($appId, [], $new, $context);
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
        $this->connection->transactional(
            function (Connection $transaction) use ($appId, $privileges, $requestedPrivileges): void {
                $transaction->executeStatement(
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

                $transaction->executeStatement(
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
            }
        );

        $this->eventDispatcher->dispatch(new AppPermissionsUpdated($appId, $privileges, $context));
    }
}
