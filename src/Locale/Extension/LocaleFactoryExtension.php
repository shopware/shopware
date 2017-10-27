<?php declare(strict_types=1);

namespace Shopware\Locale\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Locale\Event\LocaleBasicLoadedEvent;
use Shopware\Locale\Struct\LocaleBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class LocaleFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            LocaleBasicLoadedEvent::NAME => 'localeBasicLoaded',
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
        LocaleBasicStruct $locale,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function localeBasicLoaded(LocaleBasicLoadedEvent $event): void
    {
    }
}
