[titleEn]: <>(Indexing)

The DataAbstractionLayer provides several indexers to optimize the performance.

## Adding your own indexer

You can create your own indexer by implementing the `IndexerInterface`.

```php
class MyCustomerIndexer implements IndexerInterface
{
    public function index(\DateTime $timestamp): void
    {
        // will only be executed if called directly or
        // via Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerRegistry->index()
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        // will be called if a EntityWrittenContainerEvent occurs
        // please be careful to to only execute the logic for the required entites
        // e.g.
        $productEvent = $event->getEventByDefinition(ProductDefinition::class);
        if (!$productEvent) {
            return;
        }
    }
}
```

Your service definition needs to be tagged as
`shopware.dal_indexing.indexer`.

## Child Count indexer

The child count indexer is helpful when your entity has parent/child relations. To make use of the child count indexer, your entity has to have a `ChildrenAssociationField` and a `ChildCountField`. If these two requirements are met, the `Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\ChildCountIndexer` will automatically update the child_count for your entities if you create a new entity one or change an existing one.

*Note: Please be aware, that the child count will only consider direct children 
and does not work recursively.*
