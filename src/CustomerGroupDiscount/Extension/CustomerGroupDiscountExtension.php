<?php

namespace Shopware\CustomerGroupDiscount\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroupDiscount\Event\CustomerGroupDiscountBasicLoadedEvent;
use Shopware\CustomerGroupDiscount\Event\CustomerGroupDiscountWrittenEvent;
use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountBasicStruct;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class CustomerGroupDiscountExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CustomerGroupDiscountBasicLoadedEvent::NAME => 'customerGroupDiscountBasicLoaded',
            CustomerGroupDiscountWrittenEvent::NAME => 'customerGroupDiscountWritten',
        ];
    }

    public function joinDependencies(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
    }

    public function getBasicFields(): array
    {
        return [];
    }

    public function hydrate(
        CustomerGroupDiscountBasicStruct $customerGroupDiscount,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function customerGroupDiscountBasicLoaded(CustomerGroupDiscountBasicLoadedEvent $event): void
    {
    }

    public function customerGroupDiscountWritten(CustomerGroupDiscountWrittenEvent $event): void
    {
    }
}
