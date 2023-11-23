---
title: Mocking repositories
date: 2023-01-04
area: core
tags: [testing, core, repository]
---

## Context
Right now it is complicated to test classes which have a dependency on a repository. This is because mocking a repository `search` or `searchIds` call requires creating empty `EntitySearchResults` or `IdSearchResults`. This leads to much boilerplate code when writing tests and faking database results. For this reason we should provide a way to mock the `search` and `searchIds` calls in a much easier way. 

Faking a search result of a repository looks like this at the moment:

```php
$result = new EntitySearchResult(
    'my-entity',
    1,
    new EntityCollection([]),
    null,
    new Criteria(),
    Context::createDefaultContext()
);

$entityRepository = $this->createMock(EntityRepository::class);
$entityRepository
    ->expects(static::once())
    ->method('search')
    ->willReturn($result);
```

## Solution
We created a `\Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer\StaticEntityRepository` which allows the developer to easily fake repository search results.  

### How to use
```php
<?php

class SomeCoreClass
{
    public function __construct(private EntityRepository $repository) {}
    
    public function foo() 
    {
        $criteria = new Criteria();
        
        $result = $this->repository->search($criteria, $context);
        
        // ...
    }
}

class SomeCoreClassTest extends TestCase
{
    public function testFoo() 
    {
        $repository = new StaticEntityRepository([
            new UnitCollection([
                new UnitEntity(),
                new UnitEntity(),
            ])
        ]);
        
        $class = new SomeCoreClass($repository);
        
        $class->foo();
        
        // some assertions
    }
}
```

The `StaticEntityRepository` constructor accepts an array of `EntitySearchResults`, `EntityCollections` or `AggregationResultCollection`. The value is the result of the search or one of the supported collections.

### Other configurations
```php
<?php

class SomeCoreClassTest extends TestCase
{
    public function testFoo() 
    {
        $repository = new StaticEntityRepository([
            new UnitCollection([
                new UnitEntity(),
            ]),
            new AggregationResultCollection([
                new AvgResult('some-aggregation', 12.0),
            ]),
            new EntitySearchResult(
                'entity', 
                1, 
                new EntityCollection(), 
                new AggregationResultCollection(), 
                new Criteria(), 
                Context::createDefaultContext()
            ),
            [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()]       
            new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()),
        ]);
        
        $class = new SomeCoreClass($repository);
        
        $class->foo();
        
        // some assertions
    }
}
````
