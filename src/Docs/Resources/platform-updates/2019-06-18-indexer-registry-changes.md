[titleEn]: <>(IndexerRegistry changes)

The `IndexerRegistry` no longer implements the `IndexerInterface`, but provides a new `IndexerRegistryInterface` and now dispatches events 
before and after all indexer have been run.

As the indexing process is a general pattern for the DAL, it has been moved from `Shopware\Core\Framework\DataAbstractionLayer\DBAL\Indexing\IndexerRegistry`
to `Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistry`. The namespace change also applies to the `IndexerInterface` and the default indexer provided by us.

## New events

* `Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryStartEvent`
* `Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryEndEvent`
