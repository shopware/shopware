<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomerMetaFieldSubscriber implements EventSubscriberInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_WRITTEN_EVENT => 'fillCustomerMetaDataFields',
        ];
    }

    public function fillCustomerMetaDataFields(EntityWrittenEvent $event): void
    {
        if ($event->getEntityName() !== OrderDefinition::ENTITY_NAME) {
            return;
        }

        $ids = [];

        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getExistence() !== null && $writeResult->getExistence()->exists()) {
                break;
            }

            $ids[] = $writeResult->getPrimaryKey();
        }

        if (empty($ids)) {
            return;
        }

        $sql = '
UPDATE `customer`
SET order_count = (
        SELECT COUNT(order.id) AS order_count
        FROM `order`
            INNER JOIN `order_customer` ON `order`.id = `order_customer`.order_id
            INNER JOIN `state_machine_state` ON `state_machine_state`.id = `order`.state_id AND `state_machine_state`.technical_name <> :cancelled_state
        WHERE `order_customer`.customer_id = `customer`.id AND `order`.version_id = :version_id
    ),
    order_total_amount = (
        SELECT SUM(`order`.amount_total) as total_amount
        FROM `order`
            INNER JOIN `order_customer` ON `order`.id = `order_customer`.order_id
            INNER JOIN `state_machine_state` ON `state_machine_state`.id = `order`.state_id AND `state_machine_state`.technical_name <> :cancelled_state
        WHERE `order_customer`.customer_id = `customer`.id AND `order`.version_id = :version_id
    ),
    last_order_date = (
        SELECT order_date_time
        FROM `order`
            INNER JOIN `order_customer` ON `order`.id = `order_customer`.order_id
            INNER JOIN `state_machine_state` ON `state_machine_state`.id = `order`.state_id AND `state_machine_state`.technical_name <> :cancelled_state
        WHERE `order_customer`.customer_id = `customer`.id AND `order`.version_id = :version_id
        ORDER BY order_date_time DESC
        LIMIT 1
    )
WHERE id IN (
    SELECT customer_id
    FROM `order_customer`
    WHERE order_id IN (:ids)
)
        ';

        RetryableQuery::retryable(function () use ($ids, $sql): void {
            $this->connection->executeStatement(
                $sql,
                [
                    'ids' => Uuid::fromHexToBytesList($ids),
                    'cancelled_state' => OrderStates::STATE_CANCELLED,
                    'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                ],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        });
    }
}
