---
title: DAL read helpers for single records, ids and existence checks
date: 2026-11-11
area: framework
tags: [dal, performance, administration]
---

## Context

Many reads in core, in extensions and in the Administration need only one of these answers:

* Does a record exist that matches the criteria?
* What is the id of the first matching record?
* What is the first matching record?
* What is the record with this id?

The DAL has no method for these questions.
Callers build them from `search()`, `searchIds()` and the criteria settings.
The cheap form needs three settings: `searchIds()` instead of `search()`, a limit of 1, and no total count.
Callers often miss one of them:

* `search()` instead of `searchIds()` loads and hydrates the whole entity, with its associations, to read one id or to check for `null`.
* Without a limit, `search()->first()` hydrates every matching row and keeps one.
* In the Administration, the JS `Criteria` uses an exact total count by default.
  The server then runs an extra `SELECT COUNT(*)` over the whole result whenever a page is full, so every `limit 1` check with a hit pays for the count.

## Decision

### PHP: `exists()`, `firstId()`, `first()` and `get()` on the repositories

We add four methods to `EntityRepository` and `SalesChannelRepository`:

```php
public function exists(Criteria $criteria, Context $context): bool;

public function firstId(Criteria $criteria, Context $context): ?string;

public function first(Criteria $criteria, Context $context): ?Entity;

public function get(string $id, Context $context, ?Criteria $criteria = null): ?Entity;
```

`SalesChannelRepository` takes a `SalesChannelContext` instead of a `Context`.

Each method clones the criteria, so the caller's object does not change.
Filters and associations always apply.

`exists()`, `firstId()` and `first()`:

* set the limit to 1 and the total count mode to `TOTAL_COUNT_MODE_NONE`,
* keep sortings and the offset,
* call the existing `searchIds()` (`exists()`, `firstId()`) or `search()` (`first()`).

`get()`:

* replaces the ids of the criteria with `[$id]`, or creates a new criteria with that id,
* removes the limit and the offset, and sets the total count mode to `TOTAL_COUNT_MODE_NONE`,
* calls `search()`, which reads the entity directly, without an id search first.

A limit or an offset would send `get()` through the entity searcher and add an id query.
The argument order matches `get()` in the Administration.
It is the most common of these reads, with about 140 call sites in core.

`firstId()`, `first()` and `get()` do not support entities with a combined primary key, such as mapping definitions.
`exists()` supports them.

```php
public function exists(Criteria $criteria, Context $context): bool
{
    $criteria = (clone $criteria)
        ->setLimit(1)
        ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

    return $this->searchIds($criteria, $context)->getIds() !== [];
}

public function firstId(Criteria $criteria, Context $context): ?string
{
    $criteria = (clone $criteria)
        ->setLimit(1)
        ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

    return $this->searchIds($criteria, $context)->firstId();
}

public function get(string $id, Context $context, ?Criteria $criteria = null): ?Entity
{
    $criteria = ($criteria === null ? new Criteria() : clone $criteria)
        ->setIds([$id])
        ->setLimit(null)
        ->setOffset(null)
        ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

    return $this->search($criteria, $context)->getEntities()->get($id);
}
```

### Administration: `exists()`, `firstId()` and `first()` on `Repository`

We add three methods to `src/core/data/repository.data.ts`, with the same read options as `search()` and `searchIds()`:

```ts
exists(criteria: Criteria, contextOrOptions?: apiContext | RepositoryCacheOptions, cacheOptions?: RepositoryCacheOptions): Promise<boolean>;

firstId(criteria: Criteria, contextOrOptions?: apiContext | RepositoryCacheOptions, cacheOptions?: RepositoryCacheOptions): Promise<string | null>;

first(criteria: Criteria, contextOrOptions?: apiContext | RepositoryCacheOptions, cacheOptions?: RepositoryCacheOptions): Promise<Entity<EntityName> | null>;
```

Each copies the criteria with `Criteria.fromCriteria()` and sets the limit to 1 and the total count mode to 0.
`exists()` and `firstId()` call `searchIds()`, and `first()` calls `search()`.

`get()` copies its criteria with `Criteria.fromCriteria()` and sets the total count mode to 0 on the copy.
It returns one entity and never reads the total.
Today it changes the ids of the caller's criteria, and after this change it leaves the caller's criteria as it is.

## Consequences

### Core

* New callers use `exists()`, `firstId()`, `first()` and `get()` for these reads.
  Existing call sites change when they are touched.

### Extensions

* `EntityRepository` and `SalesChannelRepository` are marked `@final`, so the new methods do not break subclasses.
  Decorating them is already not supported.
* `StaticEntityRepository` extends `EntityRepository`, so tests can stub the new methods with the existing search queue.
* Subscribers to criteria and search events also see the helper reads.
  `exists()` and `firstId()` show up as `searchIds` reads with a limit of 1.
* Administration plugins that mock `Repository` in Jest only need the new methods when the code under test calls them.

### Administration

* Every `get()` that finds its entity runs one query fewer on the server.
* `exists()`, `firstId()` and `first()` make the cheap read the short one.
  The JS `Criteria` default for `search()` and `searchIds()` does not change.
