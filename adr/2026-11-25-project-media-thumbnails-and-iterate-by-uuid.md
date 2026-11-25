---
title: Project media thumbnails on read and paginate DAL indexing by UUID
date: 2026-11-25
area: framework
tags: [dal, media, thumbnails, indexing, opensearch]
status: proposed
---

## Context

Storefront cover reads need thumbnail dimensions and URLs alongside the media entity.
Shopware 6.7 stores the thumbnail records in `media_thumbnail` and a PHP-serialized copy of the collection in `media.thumbnails_ro`.
The copy saves an association query on reads, but the media DAL indexer must fetch the thumbnail entities and update every media row during a full rebuild, including media with no thumbnails.

Admin OpenSearch indexing has a separate cost even though its producer only needs media IDs.
For entities without an auto-increment field, the stock DAL iterator uses offset pagination, so later pages revisit all earlier entries.
Changing the pagination fixes that repeated work, but scanning the clustered primary index still means traversing pages that store media rows.

On a 51.3-million-media fixture, the media tablespace occupied 624 GiB and InnoDB attributed 598 GiB to its clustered primary index.
These figures include the full rows and their storage overhead.
Stock admin indexing examined 62.59 billion entries to produce its first 11.19 million documents in two hours.
The DAL media indexer processed 1.13 million media in one hour.

For the full catalog, two models estimate total runtime from the start of each stock run.

| Estimate | Admin OpenSearch media | DAL media |
| --- | ---: | ---: |
| Hold throughput at the rate observed near cutoff | 16 hours | 61 hours |
| Model cumulative offset scan time as quadratic and other work as linear | 41 hours | 36 days |

The constant-rate estimate assumes the producer can sustain 798 documents/s for admin indexing and the DAL indexer can sustain 234 media/s.
The quadratic estimate accounts for the increasing number of entries skipped on each page and assumes a constant cost per examined entry.
The DAL projection is especially uncertain because its one-hour sample covered only 2.2% of the catalog. [XL measurements and estimation method](https://github.com/shopware/shopware/issues/23422)

## Decision

### Project local thumbnails in the media read

Keep `media_thumbnail` as the source of truth and expose the internal `thumbnailsRo` field as computed JSON.
The DAL field declares a `Projected` flag carrying a `ThumbnailReadProjection`.
When the DAL builds the media SELECT, the projection can add a correlated subquery that aggregates thumbnail rows for the current media ID into JSON.
The media-loaded subscriber unpacks that result into the usual thumbnail collection, including custom fields.
An explicit `thumbnails` association takes precedence, preserving its filters and extension-provided fields.
Remote thumbnail generation skips the local projection.

Retire the full DAL media indexing pass that maintained the serialized copy.
Keep write-triggered cache invalidation in the cache subscriber, which queues the media IDs before looking up affected products when asynchronous handling is requested.
Admin OpenSearch still builds its own media search documents.

### Use the existing UUID as the indexing cursor

For a definition with a single UUID primary key named `id` and no auto-increment field, read the first page in ID order and subsequent pages with `WHERE id > :lastId ORDER BY id LIMIT :limit`.
Apply the range predicate only once a real cursor exists.
Definitions already using an auto-increment cursor keep it, and composite keys retain their existing iteration strategy.

Allow eligible definitions to name an optional covering index on the DAL `PrimaryKey` flag for ID iteration.
Media opts in with a secondary index on `media(id)`: its leaf entries provide everything an ID page needs without carrying the other media columns.
The iterator can still use the primary key when the secondary index is unavailable.
Other UUID-keyed definitions gain cursor pagination without having to add an index.

### Alternative considered — an auto-increment column on media

An indexed integer `auto_increment` column on `media` would let the existing integer cursor seek directly to the next page.
InnoDB secondary entries also contain the UUID primary key, so that index could supply both the cursor and the media ID.
This is a viable media-specific solution and would avoid extending the cursor contract to accept UUIDs.

We chose the UUID cursor because the same deep-offset problem affects other DAL indexers whose entities already have a suitable UUID primary key.
Adding an integer to media would backfill and maintain a second identifier for one table while leaving those indexers on offset pagination.
Using the existing key solves pagination once across eligible definitions and leaves the extra storage decision to the tables whose measured scan cost justifies it.

## Consequences

- **Storefront reads.** Implicit cover reads keep one DAL query and perform aggregation and hydration at read time.
  On the XL fixture, warm 24-product listing responses had medians of 538 ms with the BLOB and 543 ms with the projection.
  First visits measured 893 ms and 923 ms respectively.
  The projection and explicit association produced matching thumbnail URLs.
- **Storage and migration.** Dropping the column removes the duplicate representation from the schema.
  On the fixture an instant drop took 0.6 seconds and left the physical tablespace size unchanged.
  Reclaiming that space requires a separate table rebuild.
  The covering index also costs storage and adds work on insert and delete.
  With the BLOB still present it occupied 1.19 GiB and took 89m 37s to build.
- **Indexing.** The patched UUID cursor and covering index together completed all 51.3 million admin documents in 46m 15s including queue drain, with the BLOB still present.
  This measures the combined configuration and leaves the incremental benefit of the covering index after physical compaction as a separate measurement. [Indexing benchmark](https://github.com/shopware/shopware/issues/23422)
- **Rollout.** Keep `MediaIndexer` callable as a deprecated no-op in 6.7.
  Deprecate `MediaIndexerEvent` and stop dispatching it from core.
  The 6.8 destructive migration drops the BLOB, and workers acknowledge outstanding iteration messages for the removed media indexer through the 6.8 transition.
  Upgrade workers before enabling UUID cursors and treat serialized offsets as opaque.
  New scans resume from the last UUID, and concurrent inserts behind it rely on incremental indexing.
