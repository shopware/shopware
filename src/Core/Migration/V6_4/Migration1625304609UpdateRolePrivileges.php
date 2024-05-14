<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class Migration1625304609UpdateRolePrivileges extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1625304609;
    }

    public function update(Connection $connection): void
    {
        $appNames = $this->getAllApps($connection);
        $privileges = $this->getAppPrivileges($appNames);

        $roles = $connection->fetchAllAssociative(
            'SELECT * from `acl_role` WHERE `id` IN (SELECT DISTINCT `acl_role_id` FROM `acl_user_role`)',
        );
        $updatedAt = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT);

        foreach ($roles as $role) {
            $currentPrivileges = json_decode((string) $role['privileges'], true, 512, \JSON_THROW_ON_ERROR);
            $currentPrivileges = array_merge($currentPrivileges, $privileges);
            $currentPrivileges = array_unique($currentPrivileges);

            $role['privileges'] = json_encode($currentPrivileges, \JSON_THROW_ON_ERROR);
            $role['updated_at'] = $updatedAt;

            $connection->update('acl_role', $role, ['id' => $role['id']]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * @return array<string>
     */
    private function getAllApps(Connection $connection): array
    {
        return $connection->executeQuery('SELECT name from `app`')->fetchFirstColumn();
    }

    /**
     * @param array<string> $appNames
     *
     * @return list<string>
     */
    private function getAppPrivileges(array $appNames): array
    {
        $privileges = [
            'app.all',
        ];

        foreach ($appNames as $appName) {
            $privileges = [...$privileges, ...[
                'app.' . $appName,
            ]];
        }

        return $privileges;
    }
}
