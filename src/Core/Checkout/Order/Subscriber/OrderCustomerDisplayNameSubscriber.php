<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Subscriber;

use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class OrderCustomerDisplayNameSubscriber implements EventSubscriberInterface
{
    private const SOURCES = ['firstName', 'lastName', 'company'];

    public static function getSubscribedEvents(): array
    {
        return [
            OrderCustomerDefinition::ENTITY_NAME . '.loaded' => 'loaded',
            OrderCustomerDefinition::ENTITY_NAME . '.partial_loaded' => 'loaded',
        ];
    }

    /**
     * @param EntityLoadedEvent<OrderCustomerEntity>|PartialEntityLoadedEvent $event
     */
    public function loaded(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $customer) {
            if ($customer instanceof OrderCustomerEntity) {
                $customer->assign(['displayName' => $customer->getDisplayName()]);

                continue;
            }

            // A partial read only carries the sources when it asked for the display name
            if (!$customer instanceof PartialEntity || !$this->hasSources($customer)) {
                continue;
            }

            $customer->assign(['displayName' => OrderCustomerEntity::resolveDisplayName(
                (string) $customer->get('firstName'),
                (string) $customer->get('lastName'),
                $customer->get('company')
            )]);
        }
    }

    private function hasSources(PartialEntity $customer): bool
    {
        foreach (self::SOURCES as $source) {
            if (!$customer->has($source)) {
                return false;
            }
        }

        return true;
    }
}
