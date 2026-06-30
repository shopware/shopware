---
title: Use shared Administration cache stores for user and reference data
date: 2026-06-29
area: administration
tags: [admin, cache, user-config, reference-data]
---

## Context

Administration modules repeatedly need the same user-specific configuration and reference data, such as grid filters, search preferences, currencies, taxes, languages, product number ranges, and custom field sets.

Historically, many components loaded this data directly through repositories or API services. Opening or switching modules could therefore repeat the same requests even when the data had already been loaded during the current Administration session.

## Decision

Current-user configuration is loaded and saved through the shared `adminUserConfig` store. The store performs one bulk load for the current user with `userConfigService.search(null)`, caches values by user config key, updates cached values after writes, and resets when the current user changes.

Shared reference data is loaded through `adminReferenceData`. This store centralizes session-level caching for commonly reused Administration reference data and supports forced reloads and targeted invalidation when callers know data changed.

Custom field sets are cached by entity name in `customFieldDataProviderService`, so callers that need the same entity's sets reuse the same pending or cached request.

Direct `user_config` repository access remains only for flows that intentionally read or write configuration for another user.

### Cached data

`adminUserConfig` caches all current-user config values returned by `userConfigService.search(null)`. This includes values such as grid filter settings, search preferences, dashboard/banner dismissals, plugin upload warnings, app iframe confirmation preferences, product advanced mode settings, measurement preferences, country detail settings, and notification timestamps. It is not an allowlist: the cache is keyed by the `user_config.key` value.

`adminReferenceData` caches the shared reference data that is commonly reused across modules:

- system currency
- currencies
- active languages
- taxes
- default tax rate id from `core.tax.defaultTaxRate`
- sales channel types
- global number range ids by technical name, for example `product`, `customer`, or `order`

Reference data is cached with a short time-to-live of five minutes. Pending requests are reused so concurrent callers share one request. Language-dependent collections store the API language id they were loaded with and reload when the API language changes.

`customFieldDataProviderService` caches custom field sets by entity name, for example `product`, `tax`, or `country`. It also reuses pending loads for the same entity name.

### Non-cached data

The shared caches do not replace normal entity loading. Product details, order details, customer data, module-specific listings, arbitrary repository searches, and save/reload flows must still use repositories and criteria that match the current screen.

Generated numbers and number previews are not cached. Calls such as `numberRangeService.reserve('product')`, `numberRangeService.reserve('customer')`, or document/order number reservations still reserve or preview a fresh number and must not be replaced by cached number range id lookups.

Cross-user configuration is not routed through `adminUserConfig`. Code that intentionally reads or writes another user's `user_config` entry keeps using the `user_config` repository.

System configuration outside the values explicitly exposed by `adminReferenceData` is not cached by this decision. Callers should add a dedicated reference-data method only when the value is reused across modules and has clear invalidation behavior.

### Reading cached data

Current-user configuration is read by key:

```javascript
const value = await Shopware.Store.get("adminUserConfig").get(
    "some.user.config.key",
);
```

Reference data is read through the specific load method:

```javascript
const currencies =
    await Shopware.Store.get("adminReferenceData").loadCurrencies();
const taxes = await Shopware.Store.get("adminReferenceData").loadTaxes();
const customerNumberRangeIds =
    await Shopware.Store.get("adminReferenceData").loadNumberRangeIds(
        "customer",
    );
```

Custom field sets are read through the custom field service:

```javascript
const sets =
    await this.customFieldDataProviderService.getCustomFieldSets("product");
```

Components should not call `userConfigService.search`, `userConfigService.upsert`, or a `user_config` repository directly for current-user settings.

### Updating cached data

Current-user configuration is saved through `adminUserConfig.upsert`. The store writes through `userConfigService.upsert` and merges the written values into the local cache:

```javascript
await Shopware.Store.get("adminUserConfig").upsert({
    "some.user.config.key": value,
});
```

Reference data is not updated by mutating `adminReferenceData` directly. Callers must persist changes through the owning repository or API service first. After a successful write, callers must either invalidate the affected cache or force the next load to bypass the cache.

```javascript
Shopware.Store.get("adminReferenceData").invalidateTaxes();
await Shopware.Store.get("adminReferenceData").loadTaxes(true);
```

### Invalidation

`adminUserConfig` invalidates all cached current-user config values automatically when the current user id changes. It also exposes `invalidate()` for manual full invalidation. It does not expose per-key invalidation; updating one key should use `upsert`, which updates that key in the cache.

`adminReferenceData` exposes `invalidateAll()` and targeted invalidation methods:

- `invalidateSystemCurrency()`
- `invalidateCurrencies()`
- `invalidateActiveLanguages()`
- `invalidateTaxes()`
- `invalidateDefaultTaxRateId()`
- `invalidateSalesChannelTypes()`
- `invalidateNumberRangeIds()`
- `invalidateNumberRangeIds(technicalName)`
- `invalidateProductNumberRangeIds()`

Each reference-data load method accepts `true` as a force reload argument. Use forced reloads for one-off refreshes and invalidation methods when later callers should also reload.

`customFieldDataProviderService` currently has no public invalidation method. Code that changes custom field sets and needs fresh sets in the same Administration session must add a targeted invalidation API to that service before relying on refreshed values.

## Consequences

Administration modules avoid duplicate reads for current-user configuration and common reference data during normal navigation.

Callers that change cached reference data must explicitly invalidate the affected cache or force a reload on the next load call. Otherwise, the Administration may keep using cached data until the cache expires or the page is reloaded.

The shared stores become the preferred internal access point for current-user configuration and reusable reference data in Administration code. New cached data should only be added when it is reused, stable enough for short-lived caching, and has a clear refresh or invalidation path.
