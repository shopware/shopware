<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1608624028RemoveDefaultSalesChannelAssignmentForCustomerRecoveryEvent extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1608624028;
    }

    public function update(Connection $connection): void
    {
        $customerRecoveryEvents = $connection->fetchAllAssociative('
            SELECT id FROM `event_action`
            WHERE event_name = "customer.recovery.request"
            AND action_name = "action.mail.send"
            AND updated_at IS NULL;
        ');

        if (empty($customerRecoveryEvents)) {
            return;
        }

        $customerRecoveryEvents = array_map(fn ($event) => $event['id'], $customerRecoveryEvents);

        try {
            $connection->executeStatement(
                'DELETE FROM event_action_sales_channel WHERE event_action_id IN (:eventActionIds)',
                ['eventActionIds' => $customerRecoveryEvents],
                ['eventActionIds' => ArrayParameterType::STRING]
            );
        } catch (\Exception) {
            // nth
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
