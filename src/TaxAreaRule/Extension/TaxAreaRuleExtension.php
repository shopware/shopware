<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\TaxAreaRule\Event\TaxAreaRuleBasicLoadedEvent;
use Shopware\TaxAreaRule\Struct\TaxAreaRuleBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class TaxAreaRuleExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            TaxAreaRuleBasicLoadedEvent::NAME => 'taxAreaRuleBasicLoaded',
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
        TaxAreaRuleBasicStruct $taxAreaRule,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function taxAreaRuleBasicLoaded(TaxAreaRuleBasicLoadedEvent $event): void
    {
    }
}
