<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1673420896RemoveUndefinedSalutation extends MigrationStep
{
    private const ASSOCIATION_TABLES = [
        'customer_address',
        'customer',
        'order_customer',
        'order_address',
        'newsletter_recipient',
    ];

    public function getCreationTimestamp(): int
    {
        return 1673420896;
    }

    public function update(Connection $connection): void
    {
        foreach (self::ASSOCIATION_TABLES as $table) {
            $fkName = 'fk.' . $table . '.salutation_id';

            if (!$this->indexExists($connection, $table, $fkName)) {
                continue;
            }

            // Drop FK constraints to change from restrict delete to set null on delete
            $connection->executeStatement('ALTER TABLE `' . $table . '` DROP FOREIGN KEY `' . $fkName . '`');
            $connection->executeStatement('ALTER TABLE `' . $table . '` ADD CONSTRAINT `' . $fkName . '` FOREIGN KEY (`salutation_id`) REFERENCES `salutation` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        }

        /** @var string|false $undefinedSalutationId */
        $undefinedSalutationId = $connection->fetchOne('SELECT `id` FROM `salutation` WHERE `salutation_key` = "undefined"');

        if (!$undefinedSalutationId) {
            return;
        }

        $connection->executeStatement('DELETE FROM `salutation` WHERE `id` = :id', ['id' => $undefinedSalutationId]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
