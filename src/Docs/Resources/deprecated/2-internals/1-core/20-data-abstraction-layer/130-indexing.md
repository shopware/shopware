[titleEn]: <>(Indexing)
[hash]: <>(article:dal_index)

The DataAbstractionLayer provides several indexers to optimize the performance.

## Adding your own indexer

You can create your own indexer by extending the `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer`.

```php
class MyCustomerIndexer extends \Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer
{
    public function getName(): string
    {
        return 'my.custom.indexer';
    }

    public function iterate($offset): ?\Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage
    {
        // will only be executed if called directly or
        // via \Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry::index
    }

    public function update(\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent $event): ?\Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage
    {
        // will be called if a EntityWrittenContainerEvent occurs
        // please be careful to to only execute the logic for the required entites
        // e.g.
        $ids = $event->getPrimaryKeys(ProductDefinition::ENTIY_NAME);

        if (!$ids) {
            return null;
        }
        
        return new EntityIndexingMessage($ids, null, $event->getContext());
    }

    public function handle(\Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage $message): void
    {

    }
}
```

Your service definition needs to be tagged as
`shopware.entity_indexer `.

## Child Count indexer

The child count indexer is helpful when your entity has parent/child relations. To make use of the child count indexer, your entity has to have a `ChildrenAssociationField` and a `ChildCountField`.
To fill this fields with the correct values, you only have to register an indexer for your entity and call the `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater::update` in your handle function.

*Note: Please be aware, that the child count will only consider direct children 
and does not work recursively.*
