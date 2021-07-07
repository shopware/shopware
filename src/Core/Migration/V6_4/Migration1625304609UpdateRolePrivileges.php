<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

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
            $currentPrivileges = json_decode($role['privileges']);
            $currentPrivileges = array_merge($currentPrivileges, $privileges);
            $currentPrivileges = array_unique($currentPrivileges);

            $role['privileges'] = json_encode($currentPrivileges);
            $role['updated_at'] = $updatedAt;

            $connection->update('acl_role', $role, ['id' => $role['id']]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function getAllApps(Connection $connection): array
    {
        return $connection->executeQuery('SELECT name from `app`')->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function getAppPrivileges(array $appNames): array
    {
        $privileges = [
            'app.all',
        ];

        foreach ($appNames as $appName) {
            $privileges = array_merge($privileges, [
                'app.' . $appName,
            ]);
        }

        return $privileges;
    }
}
