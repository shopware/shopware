---
title: Use shared Administration cache service entries
date: 2026-06-29
area: administration
tags: [admin, cache, user-config]
---

## Context

Administration modules repeatedly need the same current-user configuration and stable lookup data, such as grid settings, search preferences, currencies, taxes, active languages, sales channel types, number range ids, and custom field sets.

Loading these values independently causes repeated Admin API requests during normal navigation, even when the data has already been loaded in the current Administration session.

## Decision

Administration shares these reads through the generic `cacheService`, `userConfigService`, and repository read cache options:

- current-user config is cached in `userConfigService`
- reusable repository reads are cached with `repository.search(...)`, `repository.searchIds(...)`, or `repository.get(...)` plus a `cacheKey`
- non-repository values are cached through `cacheService.query(...)`

### Cached Data

`userConfigService.search(null)` caches the full `_info/config-me` payload once per current user under `['user-config', currentUserId]`. Callers read individual keys from that cached response by passing a key list to `search([...])`.

That cache covers current-user values such as:

- grid settings and grid filters
- search preferences
- notification timestamps
- product advanced mode settings
- measurement unit preferences
- banner or modal dismissal flags
- plugin and app specific `user_config` entries for the current user

Stable shared reads use cache keys with a five minute TTL:

- `['shared-data', 'system-currency', systemCurrencyId, languageId]`
- `['shared-data', 'currencies', languageId]`
- `['shared-data', 'active-languages', languageId]`
- `['shared-data', 'taxes', languageId]`
- `['shared-data', 'sales-channel-types', languageId]`
- `['shared-data', 'number-range-ids', technicalName]`
- `['shared-data', 'default-tax-rate-id']`
- `['custom-field-sets', entityName, languageId]`

Language-dependent caches include the current API language id in the key, so switching the Administration language reads and stores a separate cached entry.

### Non-Cached Data

The shared cache does not replace normal entity loading:

- detail pages and listings still fetch their own entities
- cross-user `user_config` access still uses the `user_config` repository directly
- generated numbers and previews such as `numberRangeService.reserve(...)` are never cached
- arbitrary repository queries stay uncached unless the caller deliberately adds a stable `cacheKey`

### Reading Cached Data

Read current-user config through `userConfigService`:

```javascript
const response = await Shopware.Service('userConfigService').search([
    'my-plugin.config-key',
]);

const value = response?.data?.['my-plugin.config-key'];
```

Read reusable entity data through repository cache options:

```javascript
const criteria = new Shopware.Data.Criteria(1, 500);
criteria.addSorting(Shopware.Data.Criteria.sort('name', 'ASC', false));

const currencies = await Shopware.Service('repositoryFactory')
    .create('currency')
    .search(criteria, Shopware.Context.api, {
        cacheKey: [
            'shared-data',
            'currencies',
            Shopware.Context.api.languageId ?? 'default',
        ],
        ttl: 5 * 60 * 1000,
    });
```

Read non-repository values through `cacheService.query(...)`:

```javascript
const defaultTaxRateId = await Shopware.Service('cacheService').query({
    key: ['shared-data', 'default-tax-rate-id'],
    ttl: 5 * 60 * 1000,
    fn: async () => {
        const values = await this.systemConfigApiService.getValues('core.tax');

        return values['core.tax.defaultTaxRate'] ?? null;
    },
});
```

### Updating Cached Data

Current-user config is written through `userConfigService.upsert(...)`:

```javascript
await Shopware.Service('userConfigService').upsert({
    'my-plugin.config-key': value,
});
```

`userConfigService.upsert(...)` invalidates `['user-config', currentUserId]`. The next `search(...)` call reloads the full current-user payload once and reuses it again.

Shared entity data is updated through its owning repository or API service first. After a successful write, callers must invalidate the affected cache key prefix.

```javascript
await taxRepository.save(tax, Shopware.Context.api);

Shopware.Service('cacheService').invalidateCaches({
    cacheKey: ['shared-data', 'taxes'],
});
```

### Invalidation And Reload

Use `cacheService.invalidateCaches(...)` for prefix-based invalidation:

```javascript
const cacheService = Shopware.Service('cacheService');

cacheService.invalidateCaches({
    cacheKey: ['shared-data', 'currencies'],
});

cacheService.invalidateCaches({
    cacheKey: ['custom-field-sets', 'product'],
});
```

Each cache namespace can be invalidated independently. For example, invalidating `['shared-data', 'taxes']` leaves currencies, languages, and number range ids untouched.

When a caller needs one immediate fresh read, it can bypass the current cache entry with `forceReload: true`:

```javascript
const taxes = await taxRepository.search(criteria, Shopware.Context.api, {
    cacheKey: ['shared-data', 'taxes', Shopware.Context.api.languageId ?? 'default'],
    // true bypasses the cached result for this read and stores the fresh response again
    forceReload: true,
    ttl: 5 * 60 * 1000,
});
```

Use `forceReload` for a one-off refresh when you already know the exact follow-up read. Use `invalidateCaches(...)` when later callers should also reload.

### Cache Value Ownership And Lifetime

Cached values are shared between callers. Treat a value returned from a cached repository read as read-only. A component that needs to add, remove, or reorder entries must clone the collection first; `sw-entity-single-select`, for example, clones its search result before adding its local reset option. The cache service does not deep-clone entities because that would discard entity collection behavior and make every cached read unnecessarily expensive.

Entries with a TTL are removed when the next cache query observes that they have expired. To keep search criteria with many possible values from accumulating during a long Administration session, the service keeps at most 100 entries and evicts the oldest settled entry before adding another one. Pending requests are retained so concurrent callers continue to receive the same response.

## Consequences

Administration modules reuse one generic caching behavior for stable shared reads.

Current-user config is loaded once per user session view and reused per key lookup. Shared lookup data and custom field sets avoid repeated requests across module switches while still allowing targeted invalidation and forced reloads after writes.

New cached reads should only be added when:

- the read is reused across modules or repeated during normal navigation
- the cache key can be derived deterministically
- the data has a clear invalidation or refresh path
