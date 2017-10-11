<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\ShopTemplate\Event\ShopTemplateBasicLoadedEvent;
use Shopware\ShopTemplate\Event\ShopTemplateWrittenEvent;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicStruct;

abstract class ShopTemplateExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ShopTemplateBasicLoadedEvent::NAME => 'shopTemplateBasicLoaded',
            
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
        ShopTemplateBasicStruct $shopTemplate,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void
    { }

    public function shopTemplateBasicLoaded(ShopTemplateBasicLoadedEvent $event): void
    { }

    
}