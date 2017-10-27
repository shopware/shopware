<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ShopTemplate\Event\ShopTemplateBasicLoadedEvent;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ShopTemplateExtension implements FactoryExtensionInterface, EventSubscriberInterface
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
    ): void {
    }

    public function shopTemplateBasicLoaded(ShopTemplateBasicLoadedEvent $event): void
    {
    }
}
