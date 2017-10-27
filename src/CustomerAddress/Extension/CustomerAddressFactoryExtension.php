<?php declare(strict_types=1);

namespace Shopware\CustomerAddress\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerAddress\Event\CustomerAddressBasicLoadedEvent;
use Shopware\CustomerAddress\Struct\CustomerAddressBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class CustomerAddressFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CustomerAddressBasicLoadedEvent::NAME => 'customerAddressBasicLoaded',
        ];
    }

    public function joinDependencies(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
    }

    public function getDetailFields(): array
    {
        return [];
    }

    public function getBasicFields(): array
    {
        return [];
    }

    public function hydrate(
        CustomerAddressBasicStruct $customerAddress,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function customerAddressBasicLoaded(CustomerAddressBasicLoadedEvent $event): void
    {
    }
}
