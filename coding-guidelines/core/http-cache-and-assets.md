# HTTP cache and assets

A cached page is a promise that every URL in it still works.
These rules cover files and cache entries that cached responses depend on.

## Cached responses reference assets

Storefront HTML is cached by the HTTP cache (`CacheStore`, keyed by `HttpCacheKeyGenerator`), by reverse proxies (`ReverseProxyCache` with the Varnish and Fastly gateways), by CDNs and by browsers.
That HTML keeps pointing at theme CSS and JS under `theme/<hash>/`, media files and thumbnails.
Treat every file a cached response can reference as in use, even when the database no longer points at it.
Core cannot know how long a CDN or a browser keeps a page.

Example: `ThemeCompiler` writes each compilation into a new seeded directory (`SeedingThemePathBuilder::generateNewPath()`), so the old directory stays reachable for pages cached before the switch.

## Grace periods start at the reference change

A grace period protects pages that were rendered before a reference changed, so it starts when the reference changes, not when the file was created or modified.
A theme folder written a week ago and replaced a minute ago is still used by every page cached before that minute.
`UnusedThemeDirectoryDeleter` writes a `.retired` marker once a directory is unused and untouched for 24 hours, and deletes it 24 hours after the marker.

Example: the window was once measured from the folder's modification time.
Folders were deleted minutes after a recompile and cached pages loaded 404s.

## Cleanup stays asynchronous

Delete referenced assets only from a scheduled task or message handler (`DeleteThemeFilesTask`, `DeleteThemeFilesTaskHandler`).
A command, request or compile step runs right after the reference change, which is exactly when cached pages still use the old files.

Example: the directory deleter was called from `theme:compile` and `theme:change`; the extension store lost its CSS and JS in production.

## Invalidate what you change

Every write that changes cached output must invalidate the tags of that output through `CacheInvalidator::invalidate()`, and every new cached output must register its tags through `CacheTagCollector::addTag()`.
With delayed invalidation, `InvalidateCacheTaskHandler` purges the stored tags later; that delay is expected.
In-memory caches and registries in long-running workers are reset when their configuration or app changes (`SystemConfigChangedEvent`, app activate, deactivate, uninstall, delete).

Examples: `CacheInvalidationSubscriber` did not invalidate the category route tag when a slot config changed, so shoppers kept seeing the old page; a layout preset cache was never invalidated when its app was deactivated.

## Change caching through its policy

Caching is configured through cache policies; the operator's configuration decides the cache headers ([ADR: improved HTTP cache layer](../../adr/2025-11-03-improved-http-cache-layer.md#configurable-caching-policies)).
Do not add listeners that switch caching off or rewrite cache headers for a group of requests, and do not disable caching for logged-in customers as a fix.
With `CACHE_REWORK` (the default from 6.8) pages for logged-in customers and filled carts are cached too; keep personal data off cacheable responses instead.

Examples: a subscriber disabled 404 caching for logged-in customers; a response subscriber rewrote headers the policy owns.

## Cache keys contain every input, tags stay few

A cache key contains every input that changes the value: a new `Criteria` property, app state, the scope.
A missing input serves one caller's result to another.
Keep tags and variants few: one tag per entity of a subtree, or one more `Vary` variant, splits the cache until it barely hits.

Examples: the criteria hash left out the excluded fields; a registry key left out database-backed app state, so long-running workers served stale data; the listing cache got one tag per subtree category.
