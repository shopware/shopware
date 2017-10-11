<?php declare(strict_types=1);

namespace Shopware\ProductVoteAverage\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\ProductVoteAverage\Event\ProductVoteAverageBasicLoadedEvent;
use Shopware\ProductVoteAverage\Event\ProductVoteAverageWrittenEvent;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\ProductVoteAverage\Struct\ProductVoteAverageBasicStruct;

abstract class ProductVoteAverageExtension implements ExtensionInterface, EventSubscriberInterface
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
    ): void
    { }

    public function productVoteAverageBasicLoaded(ProductVoteAverageBasicLoadedEvent $event): void
    { }

    public function productVoteAverageWritten(ProductVoteAverageWrittenEvent $event): void
{ }
}