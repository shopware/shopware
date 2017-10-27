<?php declare(strict_types=1);

namespace Shopware\Tax\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Tax\Event\TaxBasicLoadedEvent;
use Shopware\Tax\Struct\TaxBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class TaxFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            TaxBasicLoadedEvent::NAME => 'taxBasicLoaded',
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
        TaxBasicStruct $tax,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function taxBasicLoaded(TaxBasicLoadedEvent $event): void
    {
    }
}
