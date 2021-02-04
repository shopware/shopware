<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @deprecated tag:v6.4.0 - class will be removed in 6.4.0
 */
class CustomerVatIdsDeprecationUpdater
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function updateByEvent(EntityWrittenEvent $event): void
    {
        foreach ($event->getPayloads() as $payload) {
            if (isset($payload['vatIds'])) {
                $this->updateVatIdsInCustomer($payload['vatIds'], $payload['id']);
            }
        }
    }

    private function updateVatIdsInCustomer(array $vatIds, string $customerId): void
    {
        if (!empty($vatIds)) {
            $this->connection->executeUpdate(
                'UPDATE `customer_address` SET `vat_id` = :vatId
                    WHERE `customer_address`.`customer_id` = :customerId
                    AND (customer_address.vat_id <> :vatId OR customer_address.vat_id IS NULL)',
                [
                    'vatId' => $vatIds[0],
                    'customerId' => Uuid::fromHexToBytes($customerId),
                ]
            );
        } else {
            $this->connection->executeUpdate(
                'UPDATE `customer_address` SET `vat_id` = NULL WHERE `customer_id` = :customerId',
                ['customerId' => Uuid::fromHexToBytes($customerId)]
            );
        }
    }
}
