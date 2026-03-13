<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\Salutation\SalutationDefinition;

/**
 * @internal
 */
#[Package('Core')]
class Migration1773420826AddSalutationPositionColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773420826;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $columnCreated = $this->addColumn(
            $connection,
            'salutation',
            'position',
            'INT'
        );

        if ($columnCreated) {
            $this->assignDefaultPositions($connection);

            $connection->executeStatement('ALTER TABLE `salutation` MODIFY `position` INT NOT NULL');
        }
    }

    /**
     * @throws Exception
     */
    private function assignDefaultPositions(Connection $connection): void
    {
        $salutations = $connection->fetchAllAssociative(
            'SELECT `id`, `salutation_key` FROM `salutation` ORDER BY `salutation_key` ASC'
        );

        if (empty($salutations)) {
            return;
        }

        $defaults = [
            SalutationDefinition::NOT_SPECIFIED => 1,
            SalutationDefinition::MRS => 2,
            SalutationDefinition::MR => 3,
        ];
        $position = \count($defaults);

        foreach ($salutations as $salutation) {
            $key = (string) $salutation['salutation_key'];

            $positionValue = $defaults[$key] ?? ++$position;

            $connection->executeStatement(
                'UPDATE `salutation`
                 SET `position` = :position
                 WHERE `id` = :id',
                [
                    'position' => $positionValue,
                    'id' => $salutation['id'],
                ]
            );
        }
    }
}
