---
title: Introduce transactional flow actions
date: 2024-02-12
area: Core
tags: [flow, flow-action]
---

## Context
1. If flow actions want to interact with the database in a transactional manner, they need to handle it themselves by starting and committing transactions.

2. When there is a problem committing the transaction, the error will be caught and ignored by the flow dispatcher. A vague error message will be logged, but the flows will continue to execute.
This is problematic if a transaction was already started before the flow is executed. If the connection is configured without save points (which is the default with Shopware), when a nested commit fails (eg during a flow action) the connection will be marked as rollback only.
When the outer transaction attempts to commit, eg the calling code, it will be unaware of the previous inner commit failure and thus will also fail.

## Decision

We introduce a new marker interface `\Shopware\Core\Content\Flow\Dispatching\TransactionalAction` which flow actions can implement.

The flow executor will wrap any action in a database transaction which implements the interface.

Before:

```php
class SetOrderStateAction extends FlowAction implements DelayableAction
{
    public function handleFlow(StorableFlow $flow): void
    {
        $this->connection->beginTransaction();
        
        //do stuff
        
        try {
            $this->connection->commit();
        } catch (\Throwable $e) {
                
        }
    }
}
```

After:

```php

class SetOrderStateAction extends FlowAction implements DelayableAction, TransactionalAction
{
    public function handleFlow(StorableFlow $flow): void
    {        
        //do stuff - will be wrapped in a transaction
    }
}
```

You can also force the flow executor to rollback the transaction by throwing an instance of `\Shopware\Core\Content\Flow\Dispatching\TransactionFailedException`. You can use the static `because` method to create the exception from another one. Eg:

```php

class SetOrderStateAction extends FlowAction implements DelayableAction, TransactionalAction
{
    public function handleFlow(StorableFlow $flow): void
    {        
        try {
            //search for some record
            $entity = $this->repo->find(...);
        } catch (NotFoundException $e) {
            throw TransactionFailedException::because($e);
        }
    }
}
```

The transaction will be rollback if either of the following are true:

1. If Doctrine throws an instance of `Doctrine\DBAL\Exception` during commit.
2. If the action throws an instance of `TransactionFailedException` during execution.
3. If another non-handled exception is thrown during the action execution. This is to aid debugging.

If the transaction fails, then the error will be logged. Also, if the transaction has been performed inside a nested transaction without save points enabled, the exception will be rethrown.
So that the calling code knows something went wrong and is able to handle it correctly, by rolling back instead of committing. As, in this instance, the connection will be marked as rollback only.

## Consequences

When developers want to create flows which run inside of a database transaction, they should now implement the interface `\Shopware\Core\Content\Flow\Dispatching\TransactionalAction`, nothing else is required.

When an transaction commit fails and it is inside a nested transaction, the exception will be rethrown, which means that any other scheduled actions will not be executed.
    
