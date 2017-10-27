<?php declare(strict_types=1);

namespace Shopware\ProductVote\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductVote\Event\ProductVoteBasicLoadedEvent;
use Shopware\ProductVote\Struct\ProductVoteBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class ProductVoteFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
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
    ): void {
    }

    public function productVoteBasicLoaded(ProductVoteBasicLoadedEvent $event): void
    {
    }
}
