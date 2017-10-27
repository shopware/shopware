<?php declare(strict_types=1);

namespace Shopware\Customer\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Event\CustomerBasicLoadedEvent;
use Shopware\Customer\Event\CustomerDetailLoadedEvent;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class CustomerExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CustomerBasicLoadedEvent::NAME => 'customerBasicLoaded',
            CustomerDetailLoadedEvent::NAME => 'customerDetailLoaded',
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
}
