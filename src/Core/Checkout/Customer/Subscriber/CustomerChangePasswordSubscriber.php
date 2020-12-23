<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomerChangePasswordSubscriber implements EventSubscriberInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getSubscribedEvents()
    {
        return [
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
        ];
    }

    public function onCustomerWritten(EntityWrittenEvent $event): void
    {
        $payloads = $event->getPayloads();
        foreach ($payloads as $payload) {
            if (!empty($payload['password'])) {
                $this->clearLegacyPassword($payload['id']);
            }
        }
    }

    private function clearLegacyPassword(string $customerId): void
    {
        $this->connection->executeUpdate(
            'UPDATE `customer` SET `legacy_password` = null, `legacy_encoder` = null WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($customerId),
            ]
        );
    }
}
