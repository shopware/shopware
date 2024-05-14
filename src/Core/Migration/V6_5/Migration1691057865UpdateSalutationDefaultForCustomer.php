<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\Salutation\SalutationDefinition;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1691057865UpdateSalutationDefaultForCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1691057865;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $notSpecifiedId = (string) $connection->fetchOne(
            'SELECT id FROM salutation WHERE salutation_key = :notSpecified LIMIT 1',
            ['notSpecified' => SalutationDefinition::NOT_SPECIFIED]
        );

        if (!$notSpecifiedId) {
            return;
        }

        $limit = 1000;

        do {
            $updatedRowCount = $connection->executeStatement(
                '
				UPDATE customer
				SET salutation_id = :notSpecifiedId
				WHERE salutation_id IS NULL
				LIMIT :limit
			',
                ['notSpecifiedId' => $notSpecifiedId, 'limit' => $limit],
                ['limit' => \PDO::PARAM_INT]
            );
        } while ($updatedRowCount === $limit);
    }
}
