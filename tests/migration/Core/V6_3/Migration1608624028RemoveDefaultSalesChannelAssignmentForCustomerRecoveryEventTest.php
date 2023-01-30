<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1608624028RemoveDefaultSalesChannelAssignmentForCustomerRecoveryEvent;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1608624028RemoveDefaultSalesChannelAssignmentForCustomerRecoveryEvent
 */
class Migration1608624028RemoveDefaultSalesChannelAssignmentForCustomerRecoveryEventTest extends TestCase
{
    public function testNoSalesChannelIsAssignedForCustomerRecoveryEventAsDefault(): void
    {
        static::markTestSkipped('NEXT-24549: should be enabled again after NEXT-24549 is fixed');
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1608624028RemoveDefaultSalesChannelAssignmentForCustomerRecoveryEvent();
        $migration->update($connection);

        $customerRecoveryEvents = $connection->fetchAllAssociative('
            SELECT id FROM `event_action`
            WHERE event_name = "customer.recovery.request"
            AND action_name = "action.mail.send"
            AND updated_at IS NULL;
        ');

        if (empty($customerRecoveryEvents)) {
            return;
        }

        $customerRecoveryEvents = array_column($customerRecoveryEvents, 'id');

        $customerRecoveryEventSalesChannel = $connection->fetchAllAssociative(
            'SELECT * FROM event_action_sales_channel WHERE event_action_id IN (:eventActionIds)',
            ['eventActionIds' => $customerRecoveryEvents],
            ['eventActionIds' => Connection::PARAM_STR_ARRAY]
        );

        static::assertEmpty($customerRecoveryEventSalesChannel);
    }
}
