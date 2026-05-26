---
title: Import/Export refactor
date: 2026-05-20
area: core
tags: [import-export, core]
---

## Context

The existing import/export implementation has accumulated architectural debt over time. The current structure makes it difficult to evolve the feature without carrying forward behavior that is hard to understand, hard to evolve and hard to extend safely.

The main problems we want to address are (see [issue](https://github.com/shopware/shopware/issues/14864)):

* The implementation exposes too much of its internal structure as public API, which makes larger refactorings expensive and tied to major releases.
* The code for import and export mixes orchestration, format handling, mapping and writing concerns in ways that are hard to understand.
* CSV-specific flattening means many-to-many relations are handled inconsistently and are done on a per-case basis.
* Import performance suffers in part because the current flow writes entries one by one.
* Every field type depends on having an import/export specific serializer, which means some fields currently lack serializers and new fields require new serializer implementations.
* Data is always exported fully, without a way to define export filters or criteria, which limits the usefulness of exports for large datasets and specific use cases.

## Decision

We introduce a new `ImportExportV2` architecture with the following properties:

* We introduce a new namespace and set of classes for the new feature, to allow it to coexist with the current implementation and be developed iteratively without blocking other work.
* We introduce a new admin module and refactor the UI where we improve profile validation.
* We introduce a feature flag to enable the new module.
* With the next major release the new module will become the default and the old implementation will be deprecated, with a later removal planned for a future major release.
* We keep the concept of profiles as the main contract for defining import/export formats and mappings.
* We do not support imports where rows depend on entities created by earlier rows in the same file. This means we can process imports in batches without needing to worry about intra-file dependencies.
* The core transformation model is a shared `ImportExportRecord` shape used between readers, builders and writers.
* Profiles define the contract through `recordPaths`, with optional `fieldMappings` for CSV support.
* Every field type and entity is supported automatically and does not require a custom serializer.
* Export filtering uses the same JSON filter shape as the Shopware API and is translated into DAL filters by reusing Shopware's filter parsing.
* Export and import conversion are implemented through separate definition-aware builders.
* New formats can be added by implementing new readers and writers that produce and consume the same normalized `ImportExportRecord` shape.
* Extensibility is provided through focused events at criteria building, export record conversion and import payload building.
* Import processing stays chunked and resumable, including a reusable local working copy for seek-based chunk continuation.
* DAL repository writes remain the write mechanism for the import pipeline.

## Rationale

### Shared record model

We use `ImportExportRecord` as the normalized interchange format between file readers/writers and DAL-specific conversion. This gives us one common structure for:

* JSON import/export, which can naturally represent nested trees
* CSV import/export, which still needs flattening rules but can map into the same normalized structure
* Invalid-record export, which can reuse the same writers as the main import profile

This separation lets format handling stay format-specific while the actual entity mapping stays in dedicated builders.

### `recordPaths` as the profile contract

`recordPaths` are the central contract between profiles, format handling and DAL conversion. The profile describes which parts of the entity tree should be exported or imported.

The following path shapes are supported:

* scalar root and nested paths such as `productNumber` or `manufacturer.name`
* single-reference association id shortcuts such as `tax.id`
* translation-aware paths such as `translations.DEFAULT.name` or `translations.de-DE.name`
* semantic `PriceField` paths such as `price.DEFAULT.net` or `price.EUR.gross`
* plain list field paths such as `categoryTree.*`
* wildcard list paths such as `tags.*.id` or `tags.*.name`
* nested wildcard paths for example `lineItems.*.product.tags.*.name` (JSON only)

### Definition-aware builders

`ImportExportRecordBuilder` and `ImportPayloadBuilder` both traverse `recordPaths` recursively while keeping the current `EntityDefinition` in sync. This allows the builders to handle DAL-specific semantics where they occur, for example:

* `manyToOne.id` paths can prefer the stored foreign-key field
* `translations.DEFAULT.*` and locale-specific translation paths can be handled intentionally
* `PriceField` paths such as `price.DEFAULT.net` or `price.EUR.gross` can be interpreted semantically

### JSON and CSV support

* JSON is a natural fit for nested object trees and therefore benefits immediately from the shared record model 
* CSV remains intentionally more limited. We keep the current one-wildcard limitation for CSV list paths because flat columns do not yet define a clear encoding for nested lists with multiple wildcard levels. For example, a path like `orderLineItems.*.productCategories.*.name` would work in JSON but is not clearly representable in CSV without inventing an additional encoding layer for nested lists. So we do not support it for CSV. CSV profile can only define one wildcard list level, for example `orderLineItems.*.productCategories.name`.

### Adding new formats in the future

The refactored code is intentionally structured so that new formats can be introduced by implementing new readers and writers that produce and consume the same normalized `ImportExportRecord` shape. This means the core processing logic can stay format-agnostic and new formats can be added without changing the core processing code.

### Focused extension points

We add explicit extension points at the most valuable boundaries:

* `ExportCriteriaBuiltEvent`
* `ExportRecordConvertedEvent`
* `ImportPayloadBuiltEvent`

These events allow extensions to enrich export criteria, mutate converted export records and mutate final DAL write payloads without exposing the whole processor stack as an extension surface.

### Chunked import and local working copy reuse

Import readers continue to process files in chunks. To avoid recopying the managed import file for every chunk on the same worker, we create one reusable local working copy and reuses it across chunk reads for stable `fseek` / `ftell` based continuation.

This optimization is intentionally local to the current worker. If the import is handled on distributed workers, each worker would create its own local working copy so the optimization would still apply but without cross-worker file sharing complexity.

### Repository writes instead of SyncService

The import pipeline stays on `repository->upsert()` instead of using to the Sync API service.

This is intentional because the import already builds final DAL payloads for one root entity type per run, and both repository writes and `SyncService` converge on the same core DAL writer. `SyncService` mainly adds sync-operation orchestration, sync-payload pre-processing and result/behavior wrapping, but it does not provide a clearly cheaper write path.

## Consequences

The new feature yields the following benefits:

* Import/export orchestration, format handling and DAL conversion are separated more clearly.
* The architecture is simpler and easier to understand.
* Performance should improve due to batch writing.
* JSON support becomes a natural part of the architecture instead of an afterthought.
* New formats can be added more easily by implementing new readers/writers without needing to change the core processing logic.
* Focused events give extensions clear hook points without forcing them into internal processor details.

## Open questions

* **`PriceField` imports are not fully hardened yet.**
  Paths such as `price.DEFAULT.net` and `price.EUR.gross` are supported but the DAL requires a full price structure for validation (include `price.DEFAULT.linked` too), we could either enforce that in the profile or add a builder extension to fill in missing price parts.

* **`matchBy` is intentionally narrow** We could have multiple `matchBy` fields per profile, so nested associations could be matched by different fields too. 

* **Media import from remote URLs is not part of the refactor yet.**
  The old implementation supported importing media by URL, downloading the file, creating or reusing a media entity and persisting the binary into Shopware media storage. The new architecture currently has no equivalent media-specific import flow, so we still need an explicit decision on how to handle this.

## Status

This ADR is intended to gather feedback before the actual implementation work. The prototype implementation is available in the [prototype PR](https://github.com/shopware/shopware/pull/16856).

A flow chart of the new architecture is available [here](https://github.com/shopware/shopware/blob/363a88ccf01a15a6e590db61b05a206588ba5c5c/src/Core/Content/ImportExportV2/docs/flow.md).
