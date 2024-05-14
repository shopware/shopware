<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class Migration1641476963ThemeDependentIds extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'theme.viewer' => [
            'theme_child:read',
        ],
        'theme.editor' => [
            'theme_child:update',
        ],
        'theme.creator' => [
            'theme_child:create',
        ],
        'theme.deleter' => [
            'theme_child:delete',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1641476963;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `theme_child` (
              `parent_id` BINARY(16) NOT NULL,
              `child_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`parent_id`, `child_id`),
              CONSTRAINT `fk.theme_child.parent_id__theme_id` FOREIGN KEY (`parent_id`)
                REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.theme_child.child_id` FOREIGN KEY (`child_id`)
                REFERENCES `theme` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
