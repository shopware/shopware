<?php

namespace Shopware\Locale\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\FactoryExtensionInterface;
use Shopware\Locale\Event\LocaleBasicLoadedEvent;
use Shopware\Locale\Event\LocaleWrittenEvent;
use Shopware\Locale\Struct\LocaleBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class LocaleExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            LocaleBasicLoadedEvent::NAME => 'localeBasicLoaded',
            LocaleWrittenEvent::NAME => 'localeWritten',
        ];
    }

    public function joinDependencies(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
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

    public function localeWritten(LocaleWrittenEvent $event): void
    {
    }
}
