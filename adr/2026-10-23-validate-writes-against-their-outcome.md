---
title: Validate writes against their outcome
date: 2026-10-23
area: framework
tags: [dal, validation, sync-api]
---

## Context

Many checks decide whether a write is allowed by looking at what a record will hold once the write is done, for example whether a customer's email address is already in use.

Each check works this out itself, from the write's commands and its own database query.
Most of them only look at the last command of each record.
One Sync request can write the same record several times, so a later write hides an earlier one from the check.
This let a Sync request store a customer with an email address another customer already uses.

## Decision

A check judges each record as it will be after the write: all writes the request makes to it, in order, on top of what the database holds.

The DAL builds this outcome for the columns a check names, and loads what the database holds for them in one query.

The outcome holds only the records the write creates, or whose writes set one of those columns.
A record the write deletes is not part of it.

Values stay in the format they are stored in.

## Consequences

* Checks no longer merge commands or query the rows they validate themselves.
* A check that has to see every write to a record, whatever it sets, still reads the commands. Deciding who may change a webhook is one.
* A partial update of custom fields only carries the changed keys. The outcome does not merge it into the stored value yet.
