<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerDisplayNameSubscriber implements EventSubscriberInterface
{
    private const SOURCES = ['firstName', 'lastName', 'company', 'accountType'];

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerDefinition::ENTITY_NAME . '.loaded' => 'loaded',
            CustomerDefinition::ENTITY_NAME . '.partial_loaded' => 'loaded',
        ];
    }

    /**
     * @param EntityLoadedEvent<CustomerEntity>|PartialEntityLoadedEvent $event
     */
    public function loaded(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $customer) {
            if ($customer instanceof CustomerEntity) {
                $customer->assign(['displayName' => $customer->getDisplayName()]);

                continue;
            }

            // A partial read only carries the sources when it asked for the display name
            if (!$customer instanceof PartialEntity || !$this->hasSources($customer)) {
                continue;
            }

            $customer->assign(['displayName' => CustomerEntity::resolveDisplayName(
                (string) $customer->get('firstName'),
                (string) $customer->get('lastName'),
                $customer->get('company'),
                $customer->get('accountType') === CustomerEntity::ACCOUNT_TYPE_BUSINESS
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
