<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\SeoUrl\Event\SeoUrlBasicLoadedEvent;
use Shopware\SeoUrl\Event\SeoUrlWrittenEvent;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\SeoUrl\Struct\SeoUrlBasicStruct;

abstract class SeoUrlExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            SeoUrlBasicLoadedEvent::NAME => 'seoUrlBasicLoaded',
            
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
        SeoUrlBasicStruct $seoUrl,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void
    { }

    public function seoUrlBasicLoaded(SeoUrlBasicLoadedEvent $event): void
    { }

    
}