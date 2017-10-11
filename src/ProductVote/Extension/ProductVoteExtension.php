<?php declare(strict_types=1);

namespace Shopware\ProductVote\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\ProductVote\Event\ProductVoteBasicLoadedEvent;
use Shopware\ProductVote\Event\ProductVoteWrittenEvent;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\ProductVote\Struct\ProductVoteBasicStruct;

abstract class ProductVoteExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductVoteBasicLoadedEvent::NAME => 'productVoteBasicLoaded',
            
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
        ProductVoteBasicStruct $productVote,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void
    { }

    public function productVoteBasicLoaded(ProductVoteBasicLoadedEvent $event): void
    { }

    
}