<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1794227308RemoveOrderViewerDeletePrivileges extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794227308;
    }

    public function update(Connection $connection): void
    {
        foreach ($connection->iterateAssociative('SELECT id, privileges FROM acl_role') as $role) {
            $privileges = json_decode((string) $role['privileges'], true, 512, \JSON_THROW_ON_ERROR);

            // Remove order:delete from every role with order.viewer unless it also has order.editor or order.deleter.
            if (!\in_array('order.viewer', $privileges, true)
                || \in_array('order.editor', $privileges, true)
                || \in_array('order.deleter', $privileges, true)
            ) {
                continue;
            }

            // Other modules and extensions also grant version:delete; its origin is not stored.
            $updated = array_values(array_diff($privileges, ['order:delete']));
            if ($updated === $privileges) {
                continue;
            }

            $connection->update('acl_role', [
                'privileges' => json_encode($updated, \JSON_THROW_ON_ERROR),
                'updated_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], ['id' => $role['id']]);
        }
    }
}
