<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Service\AddressHasher;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[Package('checkout')]
class AddressHashSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AddressHasher $addressHasher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_ADDRESS_LOADED_EVENT => 'generateAddressHash',
            OrderEvents::ORDER_ADDRESS_LOADED_EVENT => 'generateAddressHash',
        ];
    }

    public function generateAddressHash(EntityLoadedEvent $event): void
    {
        /** @var CustomerAddressEntity|OrderAddressEntity $entity */
        foreach ($event->getEntities() as $entity) {
            $hash = $this->addressHasher->generate($entity);
            $entity->setHash($hash);
        }
    }
}
