---
title: Remove product.available_stock
date: 2026-08-11
area: inventory
tags: [inventory, stock, deprecation]
---

## Context

[2023-05-15 - Stock Manipulation API](2023-05-15-stock-api.md) replaced the two-value stock model
(`stock` plus a derived `available_stock`) with a single `stock` value that is decremented as soon
as an order is placed. Its "`ProductDefinition` updates" section deliberately kept the
`availableStock` field:

> We decide not to remove the `availableStock` field, simply deprecating it with no plan to remove.
> This is because many integrations rely on this field and it is simple for us to maintain as a
> mirror of `stock`.

Three years of running that decision show the "simple to maintain" part was wrong in practice:

* Every write to `product.stock` carries a second column write, added by
  `AvailableStockMirrorSubscriber` on `EntityWriteEvent` and by a `available_stock = stock`
  assignment inside `StockStorage::alter()` — the hottest stock statement there is.
* Custom stock implementations that decorate `AbstractStockStorage` have to remember to keep a
  column they do not own in sync. Where they forget, the two values drift apart and the mismatch
  surfaces as a support case rather than as an error.
* The value is indexed in Elasticsearch, offered as a dynamic product group filter, selected by the
  usage-data collector, shipped in the default product comparison templates and rendered as a
  read-only "Available stock" field next to "Stock" in the Administration. Each of those repeats a
  number the merchant is already looking at, and each of them is a place where the mirror can be
  observed out of sync.
* The field name keeps promising a semantic that no longer exists. `availableStock` reads as "stock
  minus open orders"; since 6.6 that is simply `stock`.

A permanently deprecated field is not free: it is a second name for one number, and it costs a write,
an index field, a UI element and a filter option to keep alive.

## Decision

We remove `product.availableStock` and the underlying `product.available_stock` column in 6.8. This
supersedes the "no plan to remove" part of the 2023 ADR; the rest of that ADR — the stock storage
API, the order stock subscriber and the single realtime `stock` value — is unchanged.

`stock` is the replacement everywhere. It has held the identical value since 6.6, so consumers
migrate by renaming the field, with no recalculation.

Preparation lands in 6.7 and the removal itself is gated behind the `v6.8.0.0` feature flag, so the
target state is testable before the major:

* The DAL field carries a `Deprecated` flag and is only registered while `v6.8.0.0` is inactive,
  which also marks it deprecated in the generated Admin API schema. The Store API schemas are
  annotated by hand.
* `ProductEntity::getAvailableStock()` / `setAvailableStock()` trigger a deprecation and will be
  removed with the property.
* The mirror writes in `AvailableStockMirrorSubscriber` and `StockStorage::alter()`, the
  Elasticsearch mapping entry, the Administration field and columns, and the dynamic product group
  filter option are skipped while `v6.8.0.0` is active.
* Stored merchant data that references the field is migrated in 6.7 rather than at the major: a
  migration rewrites unmodified Google, Idealo and Billiger product comparison templates to
  `product.stock`. Templates a merchant has edited are reported in `UPGRADE-6.8.md` as a manual
  step, because we will not rewrite hand-written Twig.

## Consequences

* One number has one name. Custom `AbstractStockStorage` implementations can no longer desynchronise
  a column they do not own, because it no longer exists.
* Every product write loses a column write, and `StockStorage::alter()` — executed per line item on
  every order transition — loses an assignment.
* The product Elasticsearch mapping loses a field; 6.8 requires a full reindex anyway.
* Integrations that read `availableStock` break at the major unless they switch to `stock`. This is
  a rename, so the migration is mechanical, and the deprecation is announced a minor ahead with a
  runtime deprecation notice on the entity getter.
* Dynamic product groups that filter on `availableStock` stop matching. The Administration shows a
  deprecation notice on such a condition from 6.7 on, using the existing product stream deprecation
  registry, and names `stock` as the replacement.
* [2022-03-25 - Available stock improvements](_superseded/2022-03-25-available-stock.md) documents
  optimisations to a calculation that no longer exists and is moved to `_superseded`.
