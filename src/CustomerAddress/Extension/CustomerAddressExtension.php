<?php

namespace Shopware\CustomerAddress\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerAddress\Event\CustomerAddressBasicLoadedEvent;
use Shopware\CustomerAddress\Event\CustomerAddressWrittenEvent;
use Shopware\CustomerAddress\Struct\CustomerAddressBasicStruct;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class CustomerAddressExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CustomerAddressBasicLoadedEvent::NAME => 'customerAddressBasicLoaded',
            CustomerAddressWrittenEvent::NAME => 'customerAddressWritten',
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

    public function customerAddressWritten(CustomerAddressWrittenEvent $event): void
    {
    }
}
