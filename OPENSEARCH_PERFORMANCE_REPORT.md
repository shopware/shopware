# OpenSearch Performance Analysis

An analysis of how Shopware 6 uses OpenSearch as secondary read storage, with recommendations for
(a) operators: how to configure OpenSearch and Shopware, and (b) maintainers: how the code could be
changed to gain read and write performance. Written for teams without prior OpenSearch operations
experience. Catalog scale considered: a few hundred documents up to millions.

All file references point to the state of this repository at the time of writing (6.7.x-dev).

---

## 1. How Shopware uses OpenSearch

### 1.1 It is OpenSearch, not Elasticsearch

Despite the bundle name `src/Elasticsearch/`, the runtime dependency is the **OpenSearch PHP
client** (`opensearch-project/opensearch-php ^2.6`, `shyim/opensearch-php-dsl` — see
`src/Elasticsearch/composer.json`). There is no Elastic client anywhere. CI tests OpenSearch 3
(plus 2 and 1 nightly); OpenSearch 1 support is dropped in 6.8 (`UPGRADE-6.8.md`). There is no
runtime version sniffing — behavior is identical across supported server versions.

### 1.2 Read path: opt-in DAL decorators, product only

- `ElasticsearchEntitySearcher` and `ElasticsearchEntityAggregator` decorate the DAL search/
  aggregation interfaces at priority 1000 (`src/Elasticsearch/DependencyInjection/services.php`).
- A query is routed to OpenSearch only when **all** of the following hold
  (`ElasticsearchHelper::allowSearch()`, `src/Elasticsearch/Framework/ElasticsearchHelper.php:85`):
  `SHOPWARE_ES_ENABLED` is true, the entity has a registered ES definition, and the criteria
  carries `Criteria::STATE_ELASTICSEARCH_AWARE` (set by the product listing, search, suggest,
  detail and product-stream code paths).
- In core exactly **one entity** is indexed: `product`
  (`src/Elasticsearch/Product/ElasticsearchProductDefinition.php`). Everything else always hits MySQL.
- Every ES entry point catches `\Throwable` and falls back to the decorated MySQL implementation
  (behavior depends on `SHOPWARE_ES_THROW_EXCEPTION`, default on: log + rethrow).
- A separate **admin search** subsystem (`src/Elasticsearch/Admin/`) maintains its own `sw-admin-*`
  indices (16 indexers: product, order, customer, media, …) with a flat 5-field document model and
  `msearch`-based global search. Optionally (feature flag `ENABLE_OPENSEARCH_FOR_ADMIN_API`) the
  Admin API entity listing can be served from these indices.

### 1.3 Index model: one multilingual index per entity

Since 6.6 there is a **single index per entity** containing all languages
(ADR: `adr/2023-04-11-new-language-inheritance-mechanism-for-opensearch.md`). Translated fields are
object fields keyed by language UUID, e.g. `name.<languageId>.search`, with per-language analyzers
(`ElasticsearchFieldBuilder::translated()`,
`src/Elasticsearch/Framework/ElasticsearchFieldBuilder.php:88`). Physical indices are timestamped
(`sw_product_<unix-ts>`) behind a stable alias (`sw_product`), swapped atomically after a full
reindex.

The product document is heavy: nested associations (`categories`, `properties`, `options`,
`manufacturer`, `visibilities`, …), one flattened integer field per sales channel
(`visibility_<salesChannelId>`), one flattened double per price rule × currency
(`cheapest_price_rule<id>_currency<id>_gross|net`), and per-language copies of every translated
text field. In production, `_source` is trimmed to `['id', 'autoIncrement']`
(`ElasticsearchProductDefinition.php:175`) — OpenSearch only ever returns ids; entity data is then
loaded from MySQL.

### 1.4 Write path

- **Full reindex** (`bin/console es:index`): iterates product ids in batches of
  `SHOPWARE_ES_INDEXING_BATCH_SIZE` (default **100**), dispatches one
  `ElasticsearchIndexingMessage` per batch to the generic `async` messenger transport. Each handled
  message SQL-fetches the documents and sends **one `_bulk` request per message**
  (`ElasticsearchIndexer::handleIndexingMessage()`,
  `src/Elasticsearch/Framework/Indexing/ElasticsearchIndexer.php:230`).
- **Progress tracking**: `elasticsearch_index_task.doc_count` is seeded with the total and
  decremented per message. A scheduled task (`CreateAliasTaskHandler`, every 5 minutes) refreshes
  the new index and, once `doc_count <= 0`, atomically swaps the alias
  (`src/Elasticsearch/Framework/Indexing/CreateAliasTaskHandler.php:82`).
- **Incremental sync**: DAL product writes flow through `ProductIndexer` →
  `ProductUpdater` → `ElasticsearchIndexer::updateIds()`, which performs the bulk request
  **synchronously inside the product-indexer message handler**, writing to the alias.
- Old timestamped indices are **not** deleted on swap — they only lose the alias and must be
  removed via `bin/console es:index:cleanup`.

### 1.5 What is not used (relevant to expectations)

No vector/semantic search, no `search_after`/PIT/scroll (plain `from`+`size`), no runtime fields,
no stored scripts (painless source is shipped inline with every request), no index lifecycle
management, no ingest pipelines. Relevance is BM25 with a `dis_max`-based query built from the
admin-configurable search config (`ProductSearchQueryBuilder`).

---

## 2. Operator guide: configuring OpenSearch and Shopware

### 2.1 Shards and replicas — the first thing to check

**Storefront indices** default to the *cluster* defaults because `SHOPWARE_ES_NUMBER_OF_SHARDS` /
`SHOPWARE_ES_NUMBER_OF_REPLICAS` default to empty and null values are stripped from the create-index
body (`elasticsearch.yaml:314`, `IndexCreator::__construct`,
`src/Elasticsearch/Framework/Indexing/IndexCreator.php:52`). On OpenSearch the cluster default is
**1 primary / 1 replica**, which is the right choice for almost everyone:

| Catalog size (products incl. variants) | Primaries | Replicas | Notes |
|---|---|---|---|
| ≤ ~1 M | 1 | 1 | A single shard easily holds this; more shards only add coordination overhead. |
| 1–10 M | 1–3 | 1–2 | Target **10–30 GB per shard**; check with `GET _cat/shards`. Add replicas for read throughput, not primaries. |
| Multi-node, read-heavy | keep primaries low | ≥ 2 | Each replica is a full copy that can serve searches; scale reads with replicas + nodes. |

**Admin indices are the trap**: `SHOPWARE_ADMIN_ES_NUMBER_OF_SHARDS` / `_REPLICAS` still default to
**"3" / "3"** (`elasticsearch.yaml:317-318`, deprecated, becomes empty in 6.8). With 16 admin
indexers that is 48 primaries + 144 replica shards for tiny indices — on a single-node cluster all
replicas stay unassigned and the cluster reports **yellow health forever**. Set explicitly:

```bash
SHOPWARE_ADMIN_ES_NUMBER_OF_SHARDS=1
SHOPWARE_ADMIN_ES_NUMBER_OF_REPLICAS=0   # or 1 on multi-node clusters
```

Note that shard/replica settings are applied at index creation — changing them requires a full
reindex (`es:index` + alias swap), except replicas which can be changed live via
`PUT <index>/_settings`.

### 2.2 JVM and node sizing

- **Heap**: set `-Xms`/`-Xmx` to ~50 % of node RAM, capped at ~30 GB (compressed-oops limit). The
  other half is needed by Lucene's file-system cache — that cache is what actually makes reads fast.
- **Swap off** (`bootstrap.memory_lock: true` or disable swap at OS level); a swapping node is
  effectively down.
- **Storage**: SSD/NVMe. OpenSearch is I/O bound during reindex and merge.
- Single-node is fine for hundreds of thousands of documents; move to 3 nodes when you need HA
  (2-node clusters cannot elect a quorum safely).
- Watch `GET _nodes/stats/jvm,breaker` — the aggregations Shopware sends (see § 3.3) can hit the
  request circuit breaker on undersized heaps.

### 2.3 Shopware configuration reference (env vars)

All defaults from `src/Elasticsearch/Resources/config/packages/elasticsearch.yaml` and
`src/Elasticsearch/DependencyInjection/Configuration.php`.

| Env var | Default | Recommendation |
|---|---|---|
| `SHOPWARE_ES_ENABLED` | off | Enable reads only after a full index exists. |
| `SHOPWARE_ES_INDEXING_ENABLED` | off | Enable together with a running messenger worker. |
| `OPENSEARCH_URL` | `localhost:9200` | Multi-host (comma-separated) is **deprecated for 6.8** — put a load balancer or coordinating-only node in front instead. |
| `SHOPWARE_ES_INDEXING_BATCH_SIZE` | 100 | Raise to **300–1000** for full reindexes (see § 4.4). |
| `SHOPWARE_ES_NUMBER_OF_SHARDS` / `_REPLICAS` | empty → cluster default (1/1) | Set explicitly so behavior is documented; see § 2.1. |
| `SHOPWARE_ADMIN_ES_NUMBER_OF_SHARDS` / `_REPLICAS` | **3 / 3** | Set to 1 / 0–1 (§ 2.1). |
| `SHOPWARE_ES_INDEX_PREFIX` | `sw` | Distinguish environments sharing one cluster. |
| `SHOPWARE_ES_THROW_EXCEPTION` | on | Keep on in staging; in production consider off so an ES outage degrades to MySQL instead of failing requests — but then **monitor the `elasticsearch` log channel**, otherwise failures are invisible. |
| `SHOPWARE_ES_EXCLUDE_SOURCE` | 0 | Leave at 0. Counter-intuitively, `0` means `_source` is *restricted* to `id`+`autoIncrement` (small, fast); `1` stores the full document source. |
| `SHOPWARE_ES_NGRAM_MIN_GRAM` / `_MAX_GRAM` | 4 / 5 | Lower min_gram = better partial matching but a much larger index and slower indexing. Keep defaults unless partial search quality demands otherwise. |
| `SHOPWARE_ES_USE_LANGUAGE_ANALYZER` | on | Keep on; enables stemming/stopwords per language. |
| `SHOPWARE_ADMIN_ES_INDEXING_BATCH_SIZE` | 1000 | Fine as is. |

YAML-only knobs under `elasticsearch:` (override in `config/packages/elasticsearch.yaml` of your
project):

| Key | Default | Recommendation |
|---|---|---|
| `search.precision_threshold` | unset | **Set to `40000`** if listing/search total counts matter (§ 3.4). |
| `search.timeout` | `5s` | Reasonable; note it is a server-side *soft* limit — it caps query collection, not the HTTP call. |
| `search.search_type` | `query_then_fetch` | Keep. |
| `search.boost.*`, `search.dismax_tie_breaker` | see yaml | Relevance tuning only, no performance impact. |
| `index_settings.max_result_window` | 10000 | Do not raise for the storefront (deep paging gets slower linearly with the offset); the admin index already uses 500 000. |
| `index_settings.mapping.total_fields.limit` | 50000 | Generous. Note every custom field is mapped **once per language** — shops with hundreds of languages × custom fields can approach mapping explosion; prune unused custom fields from search (`include_in_search`). |

### 2.4 Operational routine

1. **Initial / full reindex**: `bin/console es:index` (with running `messenger:consume` workers),
   monitor with `bin/console es:status`, aliases swap automatically within 5 minutes of completion
   (or force with `bin/console es:create:alias`).
2. **Cleanup cron**: old indices are never auto-deleted. Schedule
   `bin/console es:index:cleanup` (e.g. daily) or each full reindex will permanently grow disk
   usage by a full index copy.
3. **Slow logs** — the single most useful debugging tool for "search is slow" reports:
   ```
   PUT sw_product/_settings
   { "index.search.slowlog.threshold.query.warn": "500ms",
     "index.search.slowlog.threshold.fetch.warn": "200ms" }
   ```
4. **Monitoring**: cluster health (green/yellow/red), heap usage, `_cat/shards` sizes,
   `_nodes/stats/indices/search` query latency, and the rotating
   `var/log/elasticsearch_<env>.log` (only `error` level and up is written).
5. **Analyzer debugging**: `bin/console es:test:analyzer <term>` shows how a term is tokenized by
   every configured analyzer.

### 2.5 Client-level caveats

`ClientFactory` (`src/Elasticsearch/Framework/ClientFactory.php`) builds a plain Guzzle transport
with **no retries and no explicit connect/request timeouts** — a hanging node blocks a PHP worker
until PHP's own limits kick in. Until that is improved in code (§ 5), keep the network path between
PHP and OpenSearch short and reliable (same VPC/AZ, health-checked load balancer).

---

## 3. Read path: findings and proposed code changes

Ranked by expected impact for a large catalog.

### 3.1 Cheapest-price painless scripts run on every listing (highest impact)

The default storefront listing uses the price filter, price sorting and a price `stats`
aggregation. All three are implemented as **inline painless scripts**:

- Filter: `RangeFilter`/`EqualsFilter` on `product.cheapestPrice` becomes a `ScriptQuery`
  (`CriteriaParser.php:540`, `:552`, `:763`, `:775`) — a script filter is evaluated **per matching
  document**, cannot use the filter cache, and cannot use index structures.
- Sort: `FieldSorting('cheapestPrice')` becomes a `_script` sort (`CriteriaParser.php:1143-1161`).
- Stats: `StatsAggregation('cheapestPrice')` uses the same script (`CriteriaParser.php:345`).

The script loops over `2 × (ruleCount + 1)` accessor keys per document
(`getCheapestPriceParameters()`, `CriteriaParser.php:429`), so cost grows with the number of active
rules in the context — shops with many pricing rules pay linearly more per document, per request.

**The data to avoid this already exists in the index.** Prices are flattened at indexing time into
plain `double` fields per rule and currency
(`ElasticsearchProductDefinition::mapCheapestPrice()`). The script's only job is "pick the first
matching key" — rule priority resolution.

**Proposal**: in `CriteriaParser`, when the context has **zero or one** price rule (the common
case for anonymous storefront traffic), emit a direct `range` query / `FieldSort` on the single
resolved field (with a `should` over the rule field and the default field, `missing`-aware), and
keep the script only for multi-rule contexts. This turns the hottest per-document script in the
product into an ordinary, cacheable, index-backed query. Expect this to be the single largest read
win for catalogs in the hundreds of thousands and up.

### 3.2 Painless sources are re-read from disk and shipped inline on every request

`CriteriaParser::loadScriptContent()` (`CriteriaParser.php:1101-1118`) does `realpath` +
`is_readable` + `file_get_contents` for each needed script **on every query build**, and sends the
full script source in the request body. OpenSearch compiles and caches scripts by source hash, so
server-side cost is mostly amortized, but:

- the PHP-side file I/O is pure waste on the hot path,
- inline scripts inflate every request body,
- the script compilation cache (default 100 entries / 75 per 5 min rate limit) is shared; many
  distinct inline scripts from other tooling can evict them.

**Proposal**: register the six `.groovy` files as **stored scripts** (`PUT _scripts/<id>`) during
index setup (`IndexCreator` or a new lifecycle step) and reference them by id — the
`getScript()` return shape already supports `id` (`CriteriaParser.php:1123`). At minimum, memoize
the file contents in the parser instance.

### 3.3 Terms aggregations pinned at `size: 10000`

Every unbounded bucketing aggregation gets `size: 10000`
(`ElasticsearchHelper::MAX_SIZE_VALUE`, applied at `CriteriaParser.php:306`, `:333`, `:377`). A
storefront listing request therefore sends, among others:

- `nested` → `terms(product.properties.id, size: 10000)`
- `nested` → `terms(product.options.id, size: 10000)`
- `terms(product.manufacturerId, size: 10000)`

For catalogs with many property options this is the classic high-cardinality terms-aggregation
memory spike (each shard builds up to 10 000 buckets × several aggregations × concurrent
requests), and `PropertyListingFilterHandler` then chunks the returned ids into **up to 10
follow-up MySQL queries** (chunks of 1000,
`src/Core/Content/Product/SalesChannel/Listing/Filter/PropertyListingFilterHandler.php`).

**Proposal**: make the default aggregation size configurable
(`elasticsearch.search.max_aggregation_size`), and let the property/manufacturer filter handlers
set realistic `Aggregation::setLimit()` values. Shops can also reduce the bucket space today by
using the listing `property-whitelist` feature (which wraps the aggs in group filters).

### 3.4 Grouped total counts are approximate above ~3000 variant groups

Listings collapse variants by `displayGroup` and compute the total via a **`cardinality`**
aggregation (`ElasticsearchEntitySearcher::convertSearch()`). Without
`elasticsearch.search.precision_threshold` set, OpenSearch's default threshold of 3000 applies —
**pagination totals silently drift** once a category has more than ~3000 distinct products.

**Operator fix (available today)**: set `elasticsearch: search: precision_threshold: 40000`
(the maximum; ~2 % worst-case error above that, at a few hundred KB memory per aggregation — see
the commented hint at `elasticsearch.yaml:17`).

**Code proposal**: default `precision_threshold` to 40000 instead of unset; approximate counts are
a surprising default for e-commerce pagination.

### 3.5 Multi-language clause fan-out

When the context's language chain has more than one entry, every filter/sort/term clause on a
translated field fans out into a `dis_max` (or a translated-sorting painless script) with **one
clause per language** (`shouldUseMainContextLanguage()`, `CriteriaParser.php:1182`;
`TranslatedFieldQueryBuilder`). A full-text search over 6 configured fields × 2 tokens × 2
languages easily reaches 80+ leaf clauses, each `nested` field adding a join.

The optimization that restricts *sortable* translated fields to the main language already exists
but is gated behind the 6.8 feature flag / the legacy `ElasticsearchOptimizeSwitch` app-config
flag. **Proposal**: promote this to default behavior in 6.8 as planned, and document that
single-language sales channels (chain length 1) never pay this cost — a reason to avoid
unnecessary language fallback chains.

### 3.6 Wildcard filters

- `ContainsFilter` → `wildcard: *value*` (leading wildcard — full index scan per term,
  `CriteriaParser.php:673`)
- `SuffixFilter` → `wildcard: *value` (`CriteriaParser.php:731`)
- `PrefixFilter` degrades from cheap `prefix` to `wildcard` in the multi-language branch
  (`CriteriaParser.php:711`)

Core storefront flows don't use these against ES, but plugins and product streams do
(`ProductStreamUpdater` marks criteria ES-aware). **Proposal**: document the cost prominently for
plugin developers; longer-term, offer an opt-in `wildcard`-type mapped subfield (OpenSearch's
`wildcard` field type exists precisely for this) or reverse-keyword subfield for suffix matching.

### 3.7 Deep pagination has no guard

Reads use `from + size` with `max_result_window: 10000`. Nothing clamps
`criteria->getOffset()`, so page ~417+ (24 per page) makes OpenSearch reject the query; with
`throw_exception` off this silently falls back to a slow MySQL query, with it on the request
fails. **Proposal**: clamp/validate the offset in `ElasticsearchEntitySearcher` and return a clean
error; for API-driven exports prefer id-range iteration (as the DAL iterator does) rather than
raising `max_result_window`.

### 3.8 Smaller read-path items

- `track_total_hits: true` on every listing (exact-count mode is hardcoded in
  `PagingListingProcessor`) disables OpenSearch's early-termination optimization. A
  `TOTAL_COUNT_MODE_NEXT_PAGES`-style bounded count for late pages would help large categories.
- `XOrFilter` expands to O(n²) clauses (`CriteriaParser.php:917`) — avoid in plugins.
- `SearchConfigLoader` queries `product_search_config` via SQL on **every** search build with no
  per-request memoization; trivial but free to cache.
- `ElasticsearchRegistry::has()/get()` linearly scan the definition list per call — irrelevant at
  n = 1, worth a keyed map once plugins register more definitions.

---

## 4. Write path: findings and proposed code changes

### 4.1 No `refresh_interval` / replica tuning during full reindex (highest impact)

The standard bulk-load optimization — set `refresh_interval: -1` and `number_of_replicas: 0` on
the new index while filling it, restore afterwards — is **not implemented**. Grep confirms the only
`refresh_interval` reference is `CreateAliasTaskHandler.php:109`, which "restores" a value nobody
ever changed. During a full reindex the new index therefore refreshes every second (creating tiny
segments and merge pressure) and every replica indexes every document redundantly — even though
the index is not serving traffic yet (the alias still points at the old index).

**Proposal**: `IndexCreator::createIndex()` creates the timestamped index with
`refresh_interval: -1, number_of_replicas: 0`; `CreateAliasTaskHandler` (which already refreshes
and calls `putSettings` before the swap) restores the configured replica count and default refresh
interval. This is a small, contained change with a large effect on full-reindex duration —
commonly 2–5× faster bulk loads, and it removes replica-related indexing load from serving nodes.

### 4.2 Replica-reset bug on alias swap

`CreateAliasTaskHandler.php:105-111` posts
`number_of_replicas: $this->config['settings']['index']['number_of_replicas']` without the
null-stripping that `IndexCreator` does. With the default (unset) `SHOPWARE_ES_NUMBER_OF_REPLICAS`
this sends `number_of_replicas: null`, which OpenSearch treats as "reset to default" — harmless
today only by coincidence (default = 1 = cluster default). Any operator who set a replica count
directly on the cluster/index rather than via the env var gets it silently reverted on every
reindex. **Proposal**: strip null keys before `putSettings` (and after § 4.1 this same call is
where the real replica count is restored, so it must be correct).

### 4.3 `doc_count` is decremented before the bulk succeeds

`ElasticsearchIndexer::handleIndexingMessage()` decrements the progress counter **first**
(`ElasticsearchIndexer.php:238`), then fetches and bulks. The `async` transport retries failed
messages up to 3 times — each retry decrements again. Consequences: the counter can reach ≤ 0
while documents are still missing, letting `CreateAliasTaskHandler` **swap the alias to an
incomplete index**; a poison message (e.g. a mapper exception) marks its documents as "done" even
though they never landed.

**Proposal**: decrement after a successful bulk (and only by the number of successfully indexed
items, using the already-parsed per-item results from `parseErrors()`).

### 4.4 Batch size 100 is small for bulk throughput

One message = one `_bulk` of `indexing_batch_size` documents, default **100**
(`Configuration.php:21`). Optimal bulk sizes are usually 1–10 MB of payload; Shopware product
documents are typically 2–20 KB, so 100-doc bulks are often < 1 MB and the per-request overhead
(HTTP round trip, SQL fetch setup, message dispatch) dominates. The admin side already defaults to
1000 (`Configuration.php:86`) with the release notes noting it is "much faster".

**Operator fix (today)**: raise `SHOPWARE_ES_INDEXING_BATCH_SIZE` to 300–1000. Choose lower values
for catalogs with huge variant/property counts per product (document size grows), and remember the
same SQL fetch (`fetchProducts` runs its translation query once per language per batch) also
scales with this value — measure with `es:status` timing on a staging copy.

**Code proposal**: raise the default and/or chunk by estimated payload bytes instead of id count.

### 4.5 `es:index` buffers every message in memory before dispatching

`ElasticsearchIndexingCommand` collects **all** `ElasticsearchIndexingMessage` objects in an array
before dispatching any (`ElasticsearchIndexingCommand.php:69-77`) — only to find the last one and
call `markAsLastMessage()`. For 5 M products at batch size 100 that is 50 000 message objects (each
holding a 100-entry id array) held simultaneously, plus a long delay before workers can start.
**Proposal**: dispatch as messages are produced; determine "last" by peeking one iteration ahead,
or replace the last-message mechanism with the `doc_count == 0` condition that the alias task
already uses.

### 4.6 Scheduled alias task refreshes in-progress indices every 5 minutes

`CreateAliasTaskHandler::handleQueue()` calls `indices()->refresh()` on **every** tracked index on
every tick, *before* checking whether indexing is finished (`CreateAliasTaskHandler.php:95-98`).
During a long reindex (with § 4.1 implemented this even breaks `refresh_interval: -1`) this forces
segment creation mid-load. **Proposal**: move the refresh below the `doc_count > 0` check so only
completed indices get the final refresh.

### 4.7 Incremental updates run synchronously and can trigger index creation

`ElasticsearchIndexer::updateIds()` executes the bulk inline within the `ProductIndexer` message
handler — ES latency directly extends product-write processing. Worse, if the alias does not exist
yet it calls `init()`, which truncates the task table and creates brand-new timestamped indices for
*all* definitions as a side effect of a single product write. Admin-side indexing is likewise
synchronous inside the web request for Admin-API writes (`AdminSearchRegistry::refresh()`).
**Proposal**: dispatch incremental updates as their own async messages; guard `init()` behind the
explicit reindex command.

### 4.8 No dedicated messenger transport

`ElasticsearchIndexingMessage` shares the generic `async` transport with everything else. A full
reindex enqueues tens of thousands of messages that compete with order/mail/webhook processing,
and there is no way to throttle ES indexing concurrency independently (too many parallel workers
bulk-writing can saturate the cluster). **Proposal**: route ES indexing messages to a dedicated
transport (e.g. `async_low_priority` already exists in the messenger config) so operators can
assign workers explicitly.

### 4.9 Smaller write-path items

- **Per-item error handling**: bulk item failures are logged and thrown as one aggregate exception;
  the messenger retry then re-bulks the whole message (safe — `index` ops are idempotent upserts —
  but wasteful). Retrying only failed items would be cheap using the existing `parseErrors()` data.
- **No client retries/timeouts** (§ 2.5): add sensible Guzzle `connect_timeout`/`timeout` and a
  retry middleware for idempotent requests in `ClientFactory`.
- The storefront swap keeps old indices (needs the cleanup cron, § 2.4) while the admin swap
  deletes them immediately — unifying on "swap, verify, then delete" with a safety window would
  remove the operational footgun.

---

## 5. Prioritized action plan

**Configuration only — do these today** (no code changes, biggest bang for effort):

| # | Action | Matters from |
|---|---|---|
| 1 | Set admin shards/replicas to 1 / 0–1 (§ 2.1) | any size (cluster health) |
| 2 | Raise `SHOPWARE_ES_INDEXING_BATCH_SIZE` to 300–1000 (§ 4.4) | ~50 k docs |
| 3 | Set `elasticsearch.search.precision_threshold: 40000` (§ 3.4) | ~3 k products per category |
| 4 | Schedule `es:index:cleanup` (§ 2.4) | any size (disk) |
| 5 | Enable search slow logs + heap monitoring (§ 2.2, § 2.4) | any size (observability) |
| 6 | JVM heap ≈ 50 % RAM ≤ 30 GB, swap off, SSD (§ 2.2) | ~500 k docs |

**Code changes — small and contained:**

| # | Change | Section |
|---|---|---|
| 7 | `refresh_interval: -1` + replicas 0 during full reindex, restore on swap | § 4.1 |
| 8 | Strip null `number_of_replicas` in `CreateAliasTaskHandler::putSettings` | § 4.2 |
| 9 | Decrement `doc_count` after successful bulk | § 4.3 |
| 10 | Refresh only completed indices in the alias task | § 4.6 |
| 11 | Stored scripts / memoized script loading | § 3.2 |
| 12 | Stream message dispatch in `es:index` | § 4.5 |
| 13 | Client connect/request timeouts + retry middleware | § 2.5 |

**Code changes — larger projects:**

| # | Change | Section |
|---|---|---|
| 14 | Direct field access for cheapest-price filter/sort/stats in single-rule contexts | § 3.1 |
| 15 | Configurable aggregation sizes / limits in listing filter handlers | § 3.3 |
| 16 | Dedicated messenger transport + async incremental updates | § 4.7, § 4.8 |
| 17 | Offset guard + bounded total counts for deep pagination | § 3.7, § 3.8 |

For a catalog of a few hundred to a few thousand products, items 1–5 are all that is needed —
OpenSearch is massively oversized for that workload and the MySQL fallback would serve it fine;
the value of ES there is search quality, not speed. From roughly 100 k documents the write-path
items (7, 9, 12) determine whether a full reindex takes minutes or hours, and from roughly 500 k
documents the read-path items (14, 15, 3) dominate storefront listing latency.

---

## 6. Multi-tenant SaaS: many tenants on one cluster

This chapter covers running many Shopware installations (one set of indices and one database per
tenant) against a **shared** OpenSearch cluster. Trigger for this analysis: hitting the
`maximum shards open` error at the default `cluster.max_shards_per_node: 1000` and raising it to
~100 000 to fit 200–400 tenants.

### 6.1 Where the shard explosion comes from — do the math per tenant

With Shopware's current defaults, one tenant costs:

| Source | Indices | Shards (default settings) |
|---|---|---|
| Storefront `product` | 1 live | 1 primary + 1 replica = **2** |
| Admin search | **16** (one per admin indexer) | 3 primaries + 9 replicas = 12 each → **192** |
| Un-cleaned old reindex copies | 1 per reindex until `es:index:cleanup` | +2 per leftover |

≈ **194 shards per tenant** at defaults — 200–400 tenants is 39 000–78 000 shards, which is
exactly the trajectory that forced the limit to ~100 k. The catalog data is *not* the problem;
the deprecated admin 3/3 default (§ 2.1) and missing cleanup are. After the fixes below, the same
tenant costs **4–34 shards**, and the same fleet fits in a few thousand shards:

1. `SHOPWARE_ADMIN_ES_NUMBER_OF_SHARDS=1`, `_REPLICAS=0` (or 1) → 16–32 admin shards instead of 192.
2. Enable admin ES **only for tenants who benefit** — it is opt-in (`SHOPWARE_ADMIN_ES_ENABLED`),
   and 16 extra indices for admin quick-search on a 5 k-product shop is a poor trade. A tenant
   without admin ES costs **2 shards**.
3. The admin indexers are tagged services (`shopware.elastic.admin-searcher-index`,
   `src/Elasticsearch/DependencyInjection/services.php`) — a SaaS build can remove indexers for
   entities tenants don't use (newsletter recipients, landing pages, …) via container config.
4. Run `es:index:cleanup` per tenant right after every alias swap, not on a lazy schedule — every
   un-cleaned reindex parks a full extra index on the cluster.
5. Tier by catalog size: below roughly 10–50 k products, MySQL search serves a tenant fine
   (§ 5) — enabling ES per tenant only above a threshold is the biggest fleet-wide lever of all.

### 6.2 Why `cluster.max_shards_per_node: 100000` is the wrong lever

The limit is a **guardrail, not a tuning knob** (it counts open shards per data node ×
node count). Raising it 100× removes the only early warning before real failure modes:

- **Every shard is a full Lucene index**: heap for segment metadata, file handles, merge threads.
  Practical budget: stay in the region of the default (~1000 shards per data node, or roughly
  ≤ 25 shards per GB of heap) and scale *nodes*, not the limit.
- **Cluster state contains every index's full mapping.** Shopware product mappings are large
  (every translated field × every language, custom fields mapped per language,
  `total_fields.limit: 50000`). Thousands of indices → cluster state of many MB, republished to
  every node on every change.
- **Shopware churns cluster state at runtime**: `CustomFieldUpdater` /
  `ProductCustomFieldsUsedUpdater` issue `putMapping` on custom-field changes,
  `LanguageSubscriber` on language creation, and every tenant reindex creates and (later) deletes
  indices. 400 tenants doing this concurrently serializes through the single elected master —
  master CPU and the `pending_tasks` queue become the cluster-wide bottleneck long before data
  nodes are busy.
- **Recovery scales with shard count**: rolling upgrades, node replacement, and full-cluster
  restarts with 50–80 k shards can take hours to reach green, with rebalancing storms on the way.

### 6.3 Isolation and safety between tenants

- **Unique index prefixes are the only tenant boundary.** All lifecycle operations act on
  wildcards derived from the prefix: cleanup and `es:reset` resolve indices via
  `{prefix}_{entity}_*` patterns (`ElasticsearchOutdatedIndexDetector::getPrefixes()`,
  `src/Elasticsearch/Framework/ElasticsearchOutdatedIndexDetector.php`). Choose collision-proof
  prefixes (e.g. `sw_t<tenant-uuid>`) — never let one tenant's prefix be a prefix of another's.
- **Scope credentials per tenant** with the OpenSearch security plugin (or fine-grained access
  control on managed offerings): one user per tenant, permissions limited to index patterns
  `{prefix}_*` and `{admin_prefix}*`. Then a bug or misconfigured env var cannot read — or
  wildcard-delete via `es:reset` — another tenant's data. Note both Shopware clients accept
  credentials in the URL (`OPENSEARCH_URL=https://user:pass@host`), and the admin client shares
  the storefront `elasticsearch.ssl` block (§ 2.5), so per-tenant client certs are not possible —
  use basic auth or SigV4.
- **Per-tenant recovery**: because MySQL is the source of truth, the simplest restore for a broken
  tenant index is `es:index` from the tenant's database, not a snapshot restore. Keep cluster
  snapshots for whole-cluster DR; per-tenant snapshot restore (restore index, re-point alias)
  works but is rarely worth the runbook complexity.

### 6.4 Noisy neighbors

Index-per-tenant gives good *data* isolation — a tenant's searches only touch its own shards —
but heap, CPU, search/write thread pools and circuit breakers are shared per node:

- **Write side — reindex storms are the main hazard.** A full reindex has no throttle and shares
  the generic `async` transport (§ 4.8); a single large tenant enqueues tens of thousands of bulk
  messages. Build a fleet-level reindex orchestrator: cap concurrent tenant reindexes per cluster
  (a handful), stagger scheduled reindexes, and give ES indexing its own messenger transport +
  worker pool per tenant so one tenant's backlog can't starve the rest. The § 4.1
  (`refresh_interval: -1` during load) and § 4.6 (no refresh of in-progress indices) fixes
  multiply in value here — today the 5-minute alias task of *every* tenant refreshes its
  in-progress indices against the shared cluster.
- **Read side.** The expensive constructs from § 3 (per-document painless price scripts,
  `size: 10000` terms aggregations) consume shared node resources, and circuit breakers trip
  per node, not per tenant — one tenant's heavy catalog can cause rejections for everyone.
  Mitigations: fix § 3.1/§ 3.3 in code; enable per-index search slow logs so the offending tenant
  is identifiable in minutes; on OpenSearch ≥ 2.18 consider **Workload Management** (query groups
  with CPU/memory limits per user — pairs naturally with per-tenant users from § 6.3); search
  backpressure (on by default in OS 2.x) helps absorb spikes.

### 6.5 Cluster architecture: cells, not one big cluster

- **Dedicated master nodes** (3, data-less) are mandatory at thousands of indices — master work
  scales with cluster-state size and churn (§ 6.2), and colocating it with query/bulk load is how
  shared clusters fall over.
- **Cap tenants per cluster and scale by adding cells.** Work the budget backwards:
  `shard budget = data_nodes × ~1000`. At ~20–35 shards/tenant (after § 6.1), a 6–10 data-node
  cell comfortably hosts **150–300 tenants**. New tenants go to the cell with headroom; big
  tenants can get their own. Cells also bound the blast radius of upgrades, red-cluster incidents
  and noisy neighbors, and keep `max_shards_per_node` at a value where the guardrail still means
  something.
- **Monitor per cell**: active shard count vs. budget, master CPU + `pending_tasks` depth, heap
  and circuit-breaker trips on data nodes, and recovery/rebalance activity during reindex windows.

### 6.6 Fleet-level checklist

| # | Action | Effect |
|---|---|---|
| 1 | Admin ES: 1 shard / 0–1 replicas, or disable per tenant | ~194 → 4–34 shards/tenant |
| 2 | `es:index:cleanup` immediately after each tenant swap | no parked duplicate indices |
| 3 | Unique prefixes + per-tenant credentials scoped to `{prefix}_*` | tenant isolation |
| 4 | Reindex orchestrator: N concurrent tenant reindexes max, staggered schedules | write-side noisy neighbor |
| 5 | Dedicated masters; cells of 150–300 tenants; keep `max_shards_per_node` near default | cluster stability |
| 6 | ES only for tenants above a catalog-size threshold | largest cost/footprint lever |
| 7 | Per-index slow logs; Workload Management (OS ≥ 2.18) with per-tenant users | read-side noisy neighbor |
