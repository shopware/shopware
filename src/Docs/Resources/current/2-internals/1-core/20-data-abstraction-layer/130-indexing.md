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
`shopware.entity_indexer`.

In case you need a more detailed indexer you have to implement the indexer completely on your own.
This service has to be tagged as `shopware.dal_indexing.indexer` instead.
You can build up on this example and check the comments to understand the underlying processes:

```php
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FooBarIndexer implements IndexerInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $entityRepository;

    /**
     * @var CacheClearer
     */
    private $cacheClearer;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $entityRepository,
        CacheClearer $cacheClearer,
        EntityCacheKeyGenerator $cacheKeyGenerator
    ) {
        // Will dispatch progress events
        $this->eventDispatcher = $eventDispatcher;
        // will generate iterators to partially update iterations
        $this->iteratorFactory = $iteratorFactory;
        // Will be the definition of the entity to update. has to be decided in the service registration
        $this->entityRepository = $entityRepository;
        // Optional but useful for clearing caches after updating entities
        $this->cacheClearer = $cacheClearer;
        // Needed for the cache clearing process
        $this->cacheKeyGenerator = $cacheKeyGenerator;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $iterator = $this->iteratorFactory->createIterator($this->entityRepository->getDefinition());
        $context = Context::createDefaultContext();

        $this->eventDispatcher->dispatch(
            // You can customize the message to match your processes and content
            new ProgressStartedEvent('Start indexing', $iterator->fetchCount()),
            ProgressStartedEvent::NAME
        );

        while ($ids = $iterator->fetch()) {
            $this->update($ids, $context);

            // This progress events are needed for the command line usage
            $this->eventDispatcher->dispatch(
                new ProgressAdvancedEvent(count($ids)),
                ProgressAdvancedEvent::NAME
            );
        }

        $this->eventDispatcher->dispatch(
            new ProgressFinishedEvent('Finished indexing'),
            ProgressFinishedEvent::NAME
        );
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        // Get the written event from the desired entity to update
        $nested = $event->getEventByEntityName($this->entityRepository->getDefinition()->getEntityName());

        if (!$nested instanceof EntityWrittenEvent) {
            return;
        }

        $this->update($nested->getIds(), $event->getContext());
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $iterator = $this->iteratorFactory->createIterator($this->entityRepository->getDefinition(), $lastId);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        $this->update($ids, Context::createDefaultContext());

        return $iterator->getOffset();
    }

    public static function getName(): string
    {
        // Choose your unique name
        return 'FooBar.Indexing';
    }
    
    protected function update(array $ids, Context $context): void
    {
        $tags = [];
        
        foreach ($ids as $id) {
            // Do your updating logic
            
            // Collect tags to clear
            $tags[] = $this->cacheKeyGenerator->getEntityTag($id, $this->entityRepository->getDefinition()->getEntityName());
        }
        
        // Clear partially 
        $this->cacheClearer->invalidateTags($tags);
    }
}
```

## Child Count indexer

The child count indexer is helpful when your entity has parent/child relations. To make use of the child count indexer, your entity has to have a `ChildrenAssociationField` and a `ChildCountField`.
To fill this fields with the correct values, you only have to register an indexer for your entity and call the `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater::update` in your handle function.

*Note: Please be aware, that the child count will only consider direct children 
and does not work recursively.*
