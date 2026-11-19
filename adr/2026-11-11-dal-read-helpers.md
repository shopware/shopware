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

Each method:

* clones the criteria, so the caller's object does not change,
* sets the limit to 1 and the total count mode to `TOTAL_COUNT_MODE_NONE`,
* keeps filters, sortings, the offset and the associations,
* calls the existing `searchIds()` (`exists()`, `firstId()`) or `search()` (`first()`, `get()`).

`get()` also sets the ids of the criteria to `[$id]`, or creates a new criteria with that id.
The argument order matches `get()` in the Administration.
It only makes the code shorter:
in PHP, a search by one id already loads at most one row and runs no count query.
It is the most common of these reads, with about 140 call sites in core.
Entities with a combined primary key, such as mapping definitions, keep using `search()`.

```php
public function exists(Criteria $criteria, Context $context): bool
{
    return $this->firstId($criteria, $context) !== null;
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
    $criteria = $criteria === null ? new Criteria() : clone $criteria;
    $criteria->setIds([$id]);

    return $this->first($criteria, $context);
}
```

### Administration: `exists()` and `firstId()` on `Repository`

We add two methods to `src/core/data/repository.data.ts`, with the same read options as `searchIds()`:

```ts
exists(criteria: Criteria, contextOrOptions?: apiContext | RepositoryCacheOptions, cacheOptions?: RepositoryCacheOptions): Promise<boolean>;

firstId(criteria: Criteria, contextOrOptions?: apiContext | RepositoryCacheOptions, cacheOptions?: RepositoryCacheOptions): Promise<string | null>;
```

Both copy the criteria with `Criteria.fromCriteria()`, set the limit to 1 and the total count mode to 0, and call `searchIds()`.

`get()` sets the total count mode to 0 on its criteria.
It returns one entity and never reads the total.

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
* `exists()` and `firstId()` make the cheap read the short one.
  The JS `Criteria` default for `search()` and `searchIds()` does not change.
