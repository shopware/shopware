<?php declare(strict_types=1);

namespace Shopware\ProductVoteAverage\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductVoteAverage\Event\ProductVoteAverageBasicLoadedEvent;
use Shopware\ProductVoteAverage\Event\ProductVoteAverageWrittenEvent;
use Shopware\ProductVoteAverage\Struct\ProductVoteAverageBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ProductVoteAverageFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductVoteAverageBasicLoadedEvent::NAME => 'productVoteAverageBasicLoaded',
            ProductVoteAverageWrittenEvent::NAME => 'productVoteAverageWritten',
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
        ProductVoteAverageBasicStruct $productVoteAverage,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function productVoteAverageBasicLoaded(ProductVoteAverageBasicLoadedEvent $event): void
    {
    }

    public function productVoteAverageWritten(ProductVoteAverageWrittenEvent $event): void
    {
    }
}
