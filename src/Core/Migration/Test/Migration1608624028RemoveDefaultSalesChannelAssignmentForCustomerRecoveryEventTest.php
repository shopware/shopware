<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1608624028RemoveDefaultSalesChannelAssignmentForCustomerRecoveryEvent;

class Migration1608624028RemoveDefaultSalesChannelAssignmentForCustomerRecoveryEventTest extends TestCase
{
    use KernelTestBehaviour;

    public function testNoSalesChannelIsAssignedForCustomerRecoveryEventAsDefault(): void
    {
        /** @var Connection $connection */
        $connection = $this->getContainer()->get(Connection::class);

        $migration = new Migration1608624028RemoveDefaultSalesChannelAssignmentForCustomerRecoveryEvent();
        $migration->update($connection);

        $customerRecoveryEvents = $connection->fetchAll('
            SELECT id FROM `event_action`
            WHERE event_name = "customer.recovery.request"
            AND action_name = "action.mail.send"
            AND updated_at IS NULL;
        ');

        if (empty($customerRecoveryEvents)) {
            return;
        }

        $customerRecoveryEvents = array_map(function ($event) {
            return $event['id'];
        }, $customerRecoveryEvents);

        $customerRecoveryEventSalesChannel = $connection->fetchAll(
            'SELECT * FROM event_action_sales_channel WHERE event_action_id IN (:eventActionIds)',
            ['eventActionIds' => $customerRecoveryEvents],
            ['eventActionIds' => Connection::PARAM_STR_ARRAY]
        );

        static::assertEmpty($customerRecoveryEventSalesChannel);
    }
}
