<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Elasticsearch\Framework\Event\CollectDefinitionsEvent;
use Shopware\Elasticsearch\Framework\Event\CreateIndexingCriteriaEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductElasticsearchSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CollectDefinitionsEvent::class => 'register',
            CreateIndexingCriteriaEvent::class => 'buildCriteria',
        ];
    }

    public function register(CollectDefinitionsEvent $event): void
    {
        $event->add(ProductDefinition::class);
    }

    public function buildCriteria(CreateIndexingCriteriaEvent $event): void
    {
        $criteria = $event->getCriteria();

        $criteria
            ->addAssociation('categoriesRo')
            ->addAssociation('properties')
            ->addAssociation('manufacturer')
            ->addAssociation('tags')
            ->addAssociation('options')
            ->addAssociation('visibilities')
        ;
    }
}
