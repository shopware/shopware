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
#[Package('framework')]
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
        $added = $this->addColumn(
            $connection,
            'salutation',
            'position',
            'INT',
            false,
            (string) SalutationDefinition::DEFAULT_POSITION
        );

        if (!$added) {
            return;
        }

        $this->assignDefaultPositions($connection);
    }

    /**
     * @throws Exception
     */
    private function assignDefaultPositions(Connection $connection): void
    {
        $defaultPositions = [
            SalutationDefinition::NOT_SPECIFIED => 1,
            SalutationDefinition::MRS => 2,
            SalutationDefinition::MR => 3,
        ];

        foreach ($defaultPositions as $key => $position) {
            $connection->executeStatement(
                'UPDATE `salutation`
                 SET `position` = :position
                 WHERE `salutation_key` = :key',
                [
                    'position' => $position,
                    'key' => $key,
                ]
            );
        }
    }
}
