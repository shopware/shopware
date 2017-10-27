<?php declare(strict_types=1);

namespace Shopware\Currency\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Event\CurrencyBasicLoadedEvent;
use Shopware\Currency\Event\CurrencyDetailLoadedEvent;
use Shopware\Currency\Struct\CurrencyBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class CurrencyExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CurrencyBasicLoadedEvent::NAME => 'currencyBasicLoaded',
            CurrencyDetailLoadedEvent::NAME => 'currencyDetailLoaded',
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
        CurrencyBasicStruct $currency,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function currencyBasicLoaded(CurrencyBasicLoadedEvent $event): void
    {
    }

    public function currencyDetailLoaded(CurrencyDetailLoadedEvent $event): void
    {
    }
}
