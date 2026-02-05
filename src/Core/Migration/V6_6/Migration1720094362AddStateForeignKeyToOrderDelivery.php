<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1720094362AddStateForeignKeyToOrderDelivery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1720094362;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            UPDATE `order_delivery`
            SET `state_id` = (SELECT `initial_state_id` FROM `state_machine` WHERE `technical_name` = 'order_delivery.state')
            WHERE `state_id` NOT IN (SELECT `id` FROM `state_machine_state` WHERE `state_machine_id` = (SELECT `id` FROM `state_machine` WHERE `technical_name` = 'order_delivery.state'));
        SQL);

        $foreignKeys = $connection->createSchemaManager()->introspectTableForeignKeyConstraintsByUnquotedName('order_delivery');

        if (\array_filter($foreignKeys, static fn (ForeignKeyConstraint $foreignKey) => $foreignKey->getReferencedTableName()->getUnqualifiedName()->getValue() === 'state_machine_state'
            && $foreignKey->getReferencingColumnNames()[0]->getIdentifier()->getValue() === 'state_id'
            && $foreignKey->getReferencedColumnNames()[0]->getIdentifier()->getValue() === 'id')
        ) {
            return;
        }

        $connection->executeStatement(<<<'SQL'
            ALTER TABLE `order_delivery`
            ADD CONSTRAINT `fk.order_delivery.state_id` FOREIGN KEY (`state_id`)
            REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
        SQL);
    }
}
