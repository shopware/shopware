<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1590408550AclResources extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1590408550;
    }

    public function update(Connection $connection): void
    {
        if (!$this->tableExists($connection, 'acl_resource')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `acl_role` ADD `privileges` json NULL AFTER `name`;');

        $roles = $this->getRoles($connection);

        foreach ($roles as $id => $privs) {
            $list = array_column($privs, 'priv');

            $connection->executeStatement(
                'UPDATE `acl_role` SET `privileges` = :privileges WHERE id = :id',
                [
                    'privileges' => json_encode($list, \JSON_THROW_ON_ERROR),
                    'id' => Uuid::fromHexToBytes($id),
                ]
            );
        }

        $connection->executeStatement('ALTER TABLE `acl_role` CHANGE `privileges` `privileges` json NOT NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE `acl_resource`');
    }

    /**
     * @return array<string, array{priv: string}>
     */
    private function getRoles(Connection $connection): array
    {
        $roles = $connection->fetchAllAssociative('
            SELECT LOWER(HEX(`role`.id)) as id, CONCAT(`resource`.`resource`, \':\', `resource`.`privilege`) as priv
            FROM acl_role `role`
                LEFT JOIN acl_resource `resource`
                    ON `role`.id = `resource`.acl_role_id
        ');

        return FetchModeHelper::group($roles);
    }

    private function tableExists(Connection $connection, string $table): bool
    {
        try {
            $connection->fetchOne('SELECT 1 FROM ' . $table . ' LIMIT 1');
        } catch (Exception) {
            return false;
        }

        return true;
    }
}
