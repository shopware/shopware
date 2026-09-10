<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent;
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
            CustomerDefinition::ENTITY_NAME . '.loaded' => 'onCustomerLoaded',
            CustomerDefinition::ENTITY_NAME . '.partial_loaded' => 'onCustomerLoaded',
        ];
    }

    /**
     * Written through the generic accessors so one method serves both a hydrated CustomerEntity and
     * the PartialEntity of a partial read, which has none of the typed getters.
     *
     * @param EntityLoadedEvent<CustomerEntity>|PartialEntityLoadedEvent $event
     */
    public function onCustomerLoaded(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $customer) {
            $customer->assign(['displayName' => $this->resolve($customer)]);
        }
    }

    /**
     * The company stands in only when there is no contact person, so a commercial account that has one
     * keeps showing that person and an existing shop sees no change.
     */
    private function resolve(Entity $customer): string
    {
        // getVars() and not has()/get(), because on a hydrated entity has() is a property_exists check
        // and a name the read did not select would throw on access.
        $vars = $customer->getVars();

        $personName = trim($this->string($vars, 'firstName') . ' ' . $this->string($vars, 'lastName'));

        if ($personName !== '' || $this->string($vars, 'accountType') !== CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            return $personName;
        }

        return trim($this->string($vars, 'company'));
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function string(array $vars, string $field): string
    {
        $value = $vars[$field] ?? null;

        return \is_string($value) ? $value : '';
    }
}
