<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
class CustomerAddressSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel.' . CustomerEvents::CUSTOMER_ADDRESS_LOADED_EVENT => 'salesChannelLoaded',
            'sales_channel.customer_address.partial_loaded' => 'salesChannelLoaded',
        ];
    }

    /**
     * @param SalesChannelEntityLoadedEvent<CustomerAddressEntity|PartialEntity> $event
     */
    public function salesChannelLoaded(SalesChannelEntityLoadedEvent $event): void
    {
        if (!$event->getSalesChannelContext()->getCustomer()) {
            return;
        }

        foreach ($event->getEntities() as $customerAddress) {
            $customerAddress->assign([
                'isDefaultBillingAddress' => $customerAddress->getId() === $event->getSalesChannelContext()->getCustomer()->getDefaultBillingAddressId(),
                'isDefaultShippingAddress' => $customerAddress->getId() === $event->getSalesChannelContext()->getCustomer()->getDefaultShippingAddressId(),
            ]);
        }
    }
}
