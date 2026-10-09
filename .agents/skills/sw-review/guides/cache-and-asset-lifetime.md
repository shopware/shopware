---
guide: cache-and-asset-lifetime
title: Cached pages must keep working
personas: [architecture, maintainer]
rules:
  - { id: CACHE-001, since: 2026-10-09, source: "coding-guidelines/core/http-cache-and-assets.md#references", origin: "https://github.com/shopware/shopware/issues/21010", fixture: "tests/guide/cache-and-asset-lifetime/catch-18612" }
  - { id: CACHE-002, since: 2026-10-09, source: "coding-guidelines/core/http-cache-and-assets.md#grace-period", origin: "https://github.com/shopware/shopware/issues/21010", fixture: "tests/guide/cache-and-asset-lifetime/catch-18612" }
  - { id: CACHE-003, since: 2026-10-09, source: "coding-guidelines/core/http-cache-and-assets.md#async-cleanup", origin: "https://github.com/shopware/shopware/pull/18612", fixture: "tests/guide/cache-and-asset-lifetime/catch-18612" }
  - { id: CACHE-004, since: 2026-10-09, source: "coding-guidelines/core/http-cache-and-assets.md#invalidation", origin: "https://github.com/shopware/shopware/pull/19460", fixture: "" }
  - { id: CACHE-005, since: 2026-10-09, source: "coding-guidelines/core/http-cache-and-assets.md#cache-policy", origin: "https://github.com/shopware/shopware/pull/16442", fixture: "" }
  - { id: CACHE-006, since: 2026-10-09, source: "coding-guidelines/core/http-cache-and-assets.md#cache-keys", origin: "https://github.com/shopware/shopware/pull/17680", fixture: "" }
---

## Why this guide exists

Storefront HTML lives on in the HTTP cache, reverse proxies, CDNs and browsers after the files it points at have changed. In #21010 old `theme/<hash>` folders were deleted minutes after a recompile while cached pages still referenced them; CSS and JS returned 404 on the extension store in production. The cause was #18612, which ran the cleanup inside `theme:compile` and measured the grace period from the file modification time.

## Check

- **CACHE-001** Look for code that deletes, moves or overwrites a file a cached response can reference: theme CSS and JS under `theme/<hash>/`, media files, thumbnails. Pages cached before the change keep requesting the old URL, so shoppers see an unstyled or broken storefront. Rule: [http-cache-and-assets.md#references](../../../../coding-guidelines/core/http-cache-and-assets.md#references). Example: #18612, `UnusedThemeDirectoryDeleter.php`.
- **CACHE-002** Look at where a grace period starts: it must start when the reference changes (the `.retired` marker in `UnusedThemeDirectoryDeleter`), not at the file's modification time. A file written last week and replaced a minute ago is deleted at once when measured from its mtime. Rule: [http-cache-and-assets.md#grace-period](../../../../coding-guidelines/core/http-cache-and-assets.md#grace-period). Example: #21010, the 24-hour window measured from `lastModified()`.
- **CACHE-003** Look for cleanup of referenced assets in a command, request, compile step or deploy path instead of a scheduled task or message handler. Those paths run right after the switch, exactly when cached pages still use the old files. Rule: [http-cache-and-assets.md#async-cleanup](../../../../coding-guidelines/core/http-cache-and-assets.md#async-cleanup). Example: #18612, `ThemeCompileCommand.php` and `ThemeChangeCommand.php` calling the deleter.
- **CACHE-004** Look for a write that changes cached output without `CacheInvalidator::invalidate()` on the matching tags, or new cached output without `CacheTagCollector::addTag()`, including a loader that becomes conditional and so stops adding its tags. The same applies to an in-memory cache or registry that is not reset when the config or app it is built from changes. Merchants save a change and shoppers keep seeing the old page until the cache expires. Rule: [http-cache-and-assets.md#invalidation](../../../../coding-guidelines/core/http-cache-and-assets.md#invalidation). Example: #19460, `CacheInvalidationSubscriber.php` missing the category route tag for slot config changes; #20220, an app lifecycle handler that never invalidated its preset cache; #21152, a conditional loader that lost its tags.
- **CACHE-005** Look for caching switched off or overridden from the side: a listener or subscriber that disables the HTTP cache or rewrites cache headers for a group of requests, or caching disabled for logged-in customers as a fix. It bypasses the configured cache policies, operators lose control, and every affected page gets slower. Keep personal data off cacheable responses and change caching through its policy. Rule: [http-cache-and-assets.md#cache-policy](../../../../coding-guidelines/core/http-cache-and-assets.md#cache-policy). Example: #16442 (`NotFoundSubscriber.php` disabling 404 caching for logged-in customers), #19634 (`SessionContextTokenSubscriber.php`).
- **CACHE-006** Look at the key and tags of new or changed cached data: every input that changes the result belongs in the key (a new `Criteria` property, app state, the scope), the hash is computed the same way everywhere, and tags and variants stay few. A missing input serves one caller's result to another; one tag per subtree entity or another `Vary` variant splits the cache until it barely hits. Rule: [http-cache-and-assets.md#cache-keys](../../../../coding-guidelines/core/http-cache-and-assets.md#cache-keys). Example: #17680 (excluded fields missing from the criteria hash), #20509 (`McpToolsetRegistry.php` key missing app state), #15956 (one tag per subtree category).

## Do not flag

CI covers:

- `NoAddCacheTagEventRule` (PHPStan) rejects `new AddCacheTagEvent` outside `CacheTagCollector`; do not repeat it.

Legitimate patterns:

- Deleting a theme directory in `ThemeCompiler` when the path is not seeded (`MD5ThemePathBuilder`, meant for setups that never recompile at runtime).
- Delayed invalidation: `CacheInvalidator` stores tags and `InvalidateCacheTaskHandler` purges them later. The delay is by design.
- Deleting files that no response links to: private filesystem files, import/export files, temporary files, test fixtures.
- Cleanup inside `DeleteThemeFilesTaskHandler` or another scheduled task that keeps a grace period from the reference change.
- Cache policies configured in `shopware.yaml` or per route: CACHE-005 is about code that bypasses them.

## Severity

- `blocking`, category `correctness`: correctly configured shops serve cached pages whose CSS, JS or images return 404 (CACHE-001, CACHE-003 as in #21010).
- `major`, category `correctness`: the grace period starts at the wrong point in time (CACHE-002), merchant changes stay invisible behind the cache (CACHE-004), caching is overridden outside its policy (CACHE-005), or a cache key misses an input (CACHE-006; `blocking`, category `security`, when one customer can see another customer's data).
- `minor`, category `performance`: invalidation that is correct but much broader than the change, or more tags and variants than needed (CACHE-006), which lowers the cache hit rate.

Set `requires_human: true` on a CACHE finding that also carries a SCOPE finding; the scope decision belongs to a person.

## Retired

(none yet)
