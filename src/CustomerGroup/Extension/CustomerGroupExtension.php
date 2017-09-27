<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Event\CustomerGroupBasicLoadedEvent;
use Shopware\CustomerGroup\Event\CustomerGroupDetailLoadedEvent;
use Shopware\CustomerGroup\Event\CustomerGroupWrittenEvent;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicStruct;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class CustomerGroupExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CustomerGroupBasicLoadedEvent::NAME => 'customerGroupBasicLoaded',
            CustomerGroupDetailLoadedEvent::NAME => 'customerGroupDetailLoaded',
            CustomerGroupWrittenEvent::NAME => 'customerGroupWritten',
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
        CustomerGroupBasicStruct $customerGroup,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function customerGroupBasicLoaded(CustomerGroupBasicLoadedEvent $event): void
    {
    }

    public function customerGroupDetailLoaded(CustomerGroupDetailLoadedEvent $event): void
    {
    }

    public function customerGroupWritten(CustomerGroupWrittenEvent $event): void
    {
    }
}
