<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerSalutationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'setDefaultSalutation',
            CustomerEvents::CUSTOMER_ADDRESS_WRITTEN_EVENT => 'setDefaultSalutation',
        ];
    }

    public function setDefaultSalutation(EntityWrittenEvent $event): void
    {
        $payloads = $event->getPayloads();
        foreach ($payloads as $payload) {
            if (\array_key_exists('salutationId', $payload) && $payload['salutationId']) {
                continue;
            }

            if (!isset($payload['id'])) {
                continue;
            }

            $this->updateCustomerWithNotSpecifiedSalutation($payload['id']);
        }
    }

    private function updateCustomerWithNotSpecifiedSalutation(string $id): void
    {
        $this->connection->executeStatement(
            '
                UPDATE `customer`
                SET `salutation_id` = (
                    SELECT `id`
                    FROM `salutation`
                    WHERE `salutation_key` = :notSpecified
                    LIMIT 1
                )
                WHERE `id` = :id AND `salutation_id` is NULL
            ',
            ['id' => Uuid::fromHexToBytes($id), 'notSpecified' => SalutationDefinition::NOT_SPECIFIED]
        );
    }
}
