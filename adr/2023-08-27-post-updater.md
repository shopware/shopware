---
title: Post updater
date: 2023-08-17
area: core
tags: [indexer, update, installer]
---

## Context
We often need a way between the different Shopware versions to provide a one-time update for data. This is currently done on the way to extend an indexer to this logic and then trigger this via a migration. 
This is of course a possible way to enable certain migrations of data, but this migration is also executed again and again when the indexer is executed. 
With certain data this is critical and can lead to system errors. For example, the one-time migration of media path information.

## Decision

We implement a new `PostUpdateIndexer`. This is an extension of the `EntityIndexer` and the system can be adapted 1:1. Also, the indexing registration via database migration can be adapted 1:1. 
However, the indexer is not triggered via the `IndexerRegistry` when a full re-index or an entity written event is triggered.
These indexers are only included after the update process.
In addition to the one-time update of the data, we then often also provide a command that can be used to trigger the migration of the data again.

## Consequences

- We allow computationally intensive migrations, which need to be performed only once, to be performed after the update process.
- These one-time migrations do not affect the normal indexer process and cannot be triggered by it.
- Developers can trigger more complex migrations without worrying about the impact on the normal indexer process.
- The transition from one system to the other is very simple and can be adapted 1:1.

## Example

```php
<?php

class PostUpdateExample extends PostUpdateIndexer
{
    public function getName(): string
    {
        return 'post.update.example';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator('my_entity', $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new EntityIndexingMessage(array_values($ids), $iterator->getOffset());
    }
    
    public function handle(EntityIndexingMessage $message): void
    {
        // handle ids
    }
}
```

```php
<?php

class MigrationExample extends \Shopware\Core\Framework\Migration\MigrationStep
{
    public function update(Connection $connection): void
    {
        $this->registerIndexer($connection, 'post.update.example');
    }
}
```
