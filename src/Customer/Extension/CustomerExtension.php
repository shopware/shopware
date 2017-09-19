<?php

namespace Shopware\Customer\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Event\CustomerBasicLoadedEvent;
use Shopware\Customer\Event\CustomerDetailLoadedEvent;
use Shopware\Customer\Event\CustomerWrittenEvent;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\Framework\Factory\FactoryExtensionInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class CustomerExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CustomerBasicLoadedEvent::NAME => 'customerBasicLoaded',
            CustomerDetailLoadedEvent::NAME => 'customerDetailLoaded',
            CustomerWrittenEvent::NAME => 'customerWritten',
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
        CustomerBasicStruct $customer,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function customerBasicLoaded(CustomerBasicLoadedEvent $event): void
    {
    }

    public function customerDetailLoaded(CustomerDetailLoadedEvent $event): void
    {
    }

    public function customerWritten(CustomerWrittenEvent $event): void
    {
    }
}
