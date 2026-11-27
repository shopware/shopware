---
title: Project media thumbnails on read and paginate DAL indexing by UUID
date: 2026-11-25
area: framework
tags: [dal, media, thumbnails, indexing, opensearch]
---

## Context

Storefront cover reads need thumbnail dimensions and URLs alongside the media entity.
Shopware 6.7 stores the thumbnail records in `media_thumbnail` and a PHP-serialized copy of the collection in `media.thumbnails_ro`.
The copy saves an association query on reads, but the media DAL indexer must fetch the thumbnail entities and update every media row during a full rebuild, including media with no thumbnails.

Admin OpenSearch indexing has a separate cost even though its producer only needs media IDs.
For entities without an auto-increment field, the stock DAL iterator uses offset pagination, so later pages revisit all earlier entries.
Changing the pagination fixes that repeated work, but scanning the clustered primary index still means traversing pages that store media rows.

On a 51.3-million-media fixture, the media tablespace occupied 624 GiB and InnoDB attributed 598 GiB to its clustered primary index, including 522 GiB of leaf pages.
The serialized thumbnail collections alone accounted for 242 GiB of row payload.
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
At a fixed page size, advancing past the last ID makes the number of index entries traversed approximately linear in the catalog size.
With the blobs populated, this removed the growing OFFSET cost but the unhinted cursor still had to traverse the wide primary index and produced only 4.43 million admin documents in 30 minutes.
Definitions already using an auto-increment cursor keep it, and composite keys retain their existing iteration strategy.

Allow eligible definitions to name an optional covering index on the DAL `PrimaryKey` flag for ID iteration.
Media opts in with a secondary index on `media(id)` whose leaf entries supply everything an ID page needs.
The iterator can still use the primary key when the secondary index is unavailable.
Other UUID-keyed definitions gain cursor pagination without having to add an index.

### Clear the thumbnail column before building the iteration index

Once the projection serves reads and old workers have stopped writing serialized collections, set existing `thumbnails_ro` values to NULL in batches of 1,000 in the regular 6.7 migration.
Waiting for the destructive column drop would leave shops paying the scan cost of the old rows in the meantime.
On XL, an instant drop took 0.6 seconds and left the 522 GiB primary leaf footprint unchanged.
After explicitly clearing the restored blobs and allowing InnoDB purge to catch up, the reported primary leaf footprint was 39.3 GiB.
The tablespace file remained about 624 GiB, so the improved scan speed did not depend on returning disk space to the filesystem.

Build the covering index after this cleanup.
With the blobs still populated, an earlier run using the covering index finished in 46m 15s through queue drain.
Even with smaller clustered rows, the 1.20 GiB secondary index gives the count and UUID page queries a much narrower tree to scan.
Both cleared-table runs below indexed all 51,300,522 documents with 20 workers and zero failed messages or shards.

| Cleared XL table | Admin OpenSearch producer | Start through queue drain |
| --- | ---: | ---: |
| UUID cursor, no dedicated index | 18m 24s | 18m 39s |
| UUID cursor using the covering index | 3m 55s | 6m 10s |

Clearing the column and adding the covering index therefore address different costs.
The cleanup makes existing rows cheaper to traverse, and the covering index lets ID-only queries bypass those rows entirely.

Shopware 6.7.2.0 used the same split between regular and destructive migrations when it introduced `media_thumbnail.media_thumbnail_size_id`.
The regular migration backfilled existing thumbnails in batches of 10,000, and the destructive phase repeated the backfill before enforcing `NOT NULL`. [6.7.2.0 thumbnail-size migration](https://github.com/shopware/shopware/blob/v6.7.2.0/src/Core/Migration/V6_7/Migration1753191228AddMediaThumbnailSizeIdToMediaThumbnail.php)
For the larger serialized values cleared here, the XL trials found comparable throughput with 1,000-row batches and shorter individual writes.

Earlier regular migrations also built secondary indexes directly on populated `media` tables.
Shopware 6.6 indexed the file extension, name and hash, and 6.7 added composite indexes for upload-time and folder queries. [File indexes](https://github.com/shopware/shopware/blob/v6.7.16.0/src/Core/Migration/V6_6/Migration1738661307AddMediaIndices.php) · [Upload-time index](https://github.com/shopware/shopware/blob/v6.7.16.0/src/Core/Migration/V6_7/Migration1771342632AddCorruptedMediaHandlerIndices.php) · [Folder index](https://github.com/shopware/shopware/blob/v6.7.16.0/src/Core/Migration/V6_7/Migration1780325388AddMediaFolderCreatedAtIndex.php)

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
- **Upgrade cost.** Clearing the XL fixture took 6h 20m including batch-size experiments, so large shops should budget a maintenance window for the 6.7 migration.
  Building the secondary index took 89m 37s with populated blobs, 92m 40s after an instant drop with physically unchanged rows, and 2m 00s after clearing and purge.
  Although cleanup made the index build about 45 times faster, cleanup plus build took about 6h 22m.
  The return on that one-time cost is cheaper recurring scans and retirement of the DAL thumbnail rewrite.
  The secondary index also consumes storage and adds work on insert and delete.
- **Rollout.** Keep `MediaIndexer` callable as a deprecated no-op in 6.7.
  Deprecate `MediaIndexerEvent` and stop dispatching it from core.
  The 6.8 destructive migration drops the BLOB, and workers acknowledge outstanding iteration messages for the removed media indexer through the 6.8 transition.
  Upgrade workers before enabling UUID cursors and treat serialized offsets as opaque.
  New scans resume from the last UUID, and concurrent inserts behind it rely on incremental indexing.
