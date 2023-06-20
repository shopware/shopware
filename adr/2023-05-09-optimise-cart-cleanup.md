---
title: Optimize cart cleanup
date: 2023-05-09
area: core
tags: [performance]
---

## Context

The existing SQL snippet to delete the outdated cart entries doesn't use any database index to narrow down entries that can be deleted. 
On high traffic shops this leads to SQL query times larger than 30 seconds to find and remove these database entries.

Running 
```
EXPLAIN DELETE FROM cart
WHERE (updated_at IS NULL AND created_at <= '2023-02-01')
   OR (updated_at IS NOT NULL AND updated_at <= '2023-02-01') LIMIT 1000;
```
shows that the original sql query doesn't use an index (`possible_keys` = `NULL`)

## Decision

Reorder the query parameters so that the relevant cart entries can be narrowed down by an indexed field.

Testing the new SQL snippet by running 
```
EXPLAIN DELETE FROM cart
        WHERE created_at <= '2023-02-01'
          AND (updated_at IS NULL OR updated_at <= '2023-02-01') LIMIT 1000;
```
shows that the new query uses an index (`possible_keys` = `idx.cart.created_at`).


## Consequences

The logic stays the same but the amount of time needed to find the record drops 
dramatically, so the change results in a better performance during cart cleanup.
