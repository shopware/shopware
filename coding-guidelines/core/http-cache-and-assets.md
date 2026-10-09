# HTTP cache and assets

A cached page is a promise that every URL in it still works. This page holds
the rules for files and cache entries that cached responses depend on; the
review guide for them is
[`cache-and-asset-lifetime`](../../.agents/skills/sw-review/guides/cache-and-asset-lifetime.md).

<a id="references"></a>

## Cached responses reference assets

Storefront HTML is cached by the HTTP cache (`CacheStore`, keyed by
`HttpCacheKeyGenerator`), by reverse proxies (`ReverseProxyCache` with
`VarnishReverseProxyGateway` or `FastlyReverseProxyGateway`), by CDNs and by
browsers. That HTML keeps pointing at asset URLs: theme CSS and JS under
`theme/<hash>/`, media files and thumbnails. Treat every file that a cached
response can reference as in use, even when the database no longer points at
it. Core cannot know how long a CDN or browser keeps a page, so "nothing
references it any more" is never true at the moment of the switch. Example:
`ThemeCompiler` writes each compilation into a new seeded directory
(`SeedingThemePathBuilder::generateNewPath()`) so the old directory stays
reachable for pages cached before the switch.

<a id="grace-period"></a>

## Grace periods start at the reference change

A grace period protects cached responses that were rendered before a reference
changed. It must therefore start when the reference changes, not when the file
was created or last modified. A theme folder written a week ago and replaced a
minute ago is still used by every page cached before that minute. Record the
time of the switch and measure from there; `UnusedThemeDirectoryDeleter` writes
a `.retired` marker with the current time when it first finds a directory
unused and deletes it 24 hours after that marker. Example: #21010, where the
24-hour window was measured from the folder's file modification time, so
folders were deleted minutes after a recompile and cached pages loaded 404s.

<a id="async-cleanup"></a>

## Cleanup stays asynchronous

Delete referenced assets only from a scheduled task or message handler, such as
`DeleteThemeFilesTask` handled by `DeleteThemeFilesTaskHandler`. A command,
request or compile step runs right after the reference change, which is exactly
when cached pages still use the old files. Moving cleanup into such a path
"because the task does not run" also breaks the rule in
[platform-scope.md](platform-scope.md#environment). Example: #18612 called the
directory deleter from `theme:compile` and `theme:change`; the extension store
storefront lost its CSS and JS in production (#21010).

<a id="invalidation"></a>

## Invalidate what you change

Every write that changes cached output must invalidate the cache tags of that
output through `CacheInvalidator::invalidate()`. Every new cached output must
register its tags through `CacheTagCollector::addTag()`, so a later write can
find it. With delayed invalidation enabled, `CacheInvalidator` stores the tags and
`InvalidateCacheTaskHandler` purges them later; that delay is expected and is
not a missing invalidation. Example: #19460 added the missing invalidation of the
category route tag in `CacheInvalidationSubscriber` when a category's slot
config changed; before, merchants saved CMS changes and shoppers kept seeing
the old page. The same holds for in-memory caches and registries
in long-running workers: reset them when the configuration or the app they are
built from changes (`SystemConfigChangedEvent`, app activate, deactivate,
uninstall and delete). Example: #20220, where a layout preset cache was never
invalidated when its app was deactivated.

<a id="cache-policy"></a>

## Change caching through its policy

Caching is configured through cache policies, and the operator's configuration
is the source of truth for cache headers
([ADR improved HTTP cache layer](../../adr/2025-11-03-improved-http-cache-layer.md#configurable-caching-policies)).
Do not add listeners or subscribers that switch caching off or rewrite cache
headers for a group of requests, and do not disable caching for logged-in
customers as a fix. Pages for logged-in customers and filled carts are cached by
default; keep personal data off cacheable responses instead. Examples: #16442
disabled 404 caching for logged-in customers; #19634 added a listener of the
kind the cache rework had removed, and #18835 was asked to leave the headers to
the policy.

<a id="cache-keys"></a>

## Cache keys contain every input, tags stay few

A cache key contains every input that changes the cached value: a new
`Criteria` property, app state, the scope. A missing input serves one caller's
result to another. Compute the hash the same way in every scope. Keep tags and
variants few: one tag per entity of a subtree, or one more `Vary` variant,
splits the cache until it barely hits. Examples: #17680 left excluded fields out
of the criteria hash; #20509 left database-backed app state out of a registry
key, so long-running workers served stale data; #15956 added one tag per
subtree category to the listing cache.
