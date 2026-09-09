<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerDisplayNameSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'customer.loaded' => 'onCustomerLoaded',
        ];
    }

    public function onCustomerLoaded(EntityLoadedEvent $event): void
    {
        if ($event->getName() !== CustomerDefinition::ENTITY_NAME . '.loaded') {
            return;
        }

        foreach ($event->getEntities() as $customer) {
            if (!$customer instanceof CustomerEntity) {
                continue;
            }

            $customer->setDisplayName($this->resolve($customer));
        }
    }

    /**
     * The company stands in only when there is no contact person, so a commercial account that has one
     * keeps showing that person and an existing shop sees no change.
     */
    private function resolve(CustomerEntity $customer): string
    {
        $personName = trim($customer->getFirstName() . ' ' . $customer->getLastName());

        if ($personName !== '' || !$customer->isBusinessAccount()) {
            return $personName;
        }

        return trim($customer->getCompany() ?? '');
    }
}
