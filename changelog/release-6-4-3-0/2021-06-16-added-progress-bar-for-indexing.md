---
title: Added progress bar for indexing
issue: NEXT-15366
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Added `Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer::getTotal()`, to find out the number of records of an indexer to be processed
* Added `Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer::getDecorated()`, to fulfill the decoration pattern requirements 
___
# Upgrade Information
## Update EntityIndexer implementation
Two new methods have been added to the abstract `Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer`.
* `getTotal` - Shall return the number of records to be processed by the indexer on a Full index.
* `getDecorated` - Shall return the decorated service (see decoration pattern adr).

These two methods are declared as `abstract` with the 6.5.0.0. Here is an example of how a possible implementation might look like:
```

    public function getTotal(): int
    {
        return $this
            ->iteratorFactory
            ->createIterator($this->repository->getDefinition(), $offset)
            ->fetchCount();
        
        // alternate    
        return $this->connection->fetchOne('SELECT COUNT(*) FROM product');
    }

    public function getDecorated(): EntityIndexer
    {
        // if you implement an own indexer
        throw new DecorationPatternException(self::class);
        
        // if you decorate a core indexer
        return $this->decorated;
    }

```
