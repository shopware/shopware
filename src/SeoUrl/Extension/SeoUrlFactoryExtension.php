<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\SeoUrl\Event\SeoUrlBasicLoadedEvent;
use Shopware\SeoUrl\Struct\SeoUrlBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class SeoUrlFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
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
    ): void {
    }

    public function seoUrlBasicLoaded(SeoUrlBasicLoadedEvent $event): void
    {
    }
}
