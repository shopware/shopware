# 6.7.12.0 (upcoming)

## Storefront

### Storefront cache hash no longer varies by language

The HTTP cache hash no longer includes the language id for storefront requests, because the storefront language is derived from the resolved domain URL.
Store API requests still include the language id in the cache hash, as the same Store API URL can return different languages via the `sw-language-id` header.

### Central extension point for content before/after list prices

A new template `@Storefront/storefront/component/product/list-price-affix.html.twig` is rendered inside every list price display (product box, product detail buy widget, advanced pricing table). It replaces the deprecated `listing.beforeListPrice` / `listing.afterListPrice` snippets as the single place to inject content around list prices.

Content can be provided in two ways:

- Without code: create a translation snippet with a custom key and enter that key in the new sales-channel-aware system config settings `core.listing.beforeListPriceSnippetKey` / `core.listing.afterListPriceSnippetKey`. The snippet content is rendered sanitized, wrapped in a `list-price-affix list-price-affix-{before|after}` span.
- In a theme or plugin: override the block `component_list_price_affix_content` once; the `position` variable (`before` / `after`) allows position-specific output.

### Thumbnail `sizes` attribute now emits a value for the XXL breakpoint

The auto-generated `sizes` attribute produced by `thumbnail.html.twig` now includes a value for the XXL breakpoint. The `xxl` key is the open-ended top (`container / columns`), and `xl` is a closed range bounded by `breakpoint.xxl - 1`, matching the pattern used by smaller breakpoints. Templates that pass a manual `sizes` map to `sw_thumbnails` should add an `xxl` entry to keep parity.

### Storefront XHR login failures now keep HTTP 403

Storefront requests that require a logged-in customer no longer redirect to the login page for XMLHttpRequests when the customer session is no longer valid.
The original `403 Forbidden` response is preserved.
Regular page requests still redirect to the login page.
This prevents expired sessions from creating redirect chains from XHR endpoints to page controllers and fixes the follow-up failure where the redirected XHR request reaches the login page, which does not allow XHR access.
JavaScript clients can now handle the failed unauthenticated XHR response explicitly.

### Mail templates can access storefront theme configuration

Mail templates rendered for a sales channel now receive a temporary `salesChannelContext` and the assigned `themeId`.
This allows Twig helpers such as `theme_config()` to resolve storefront theme configuration in mails without replacing the existing core `context` variable.
The shared `MailTemplateRenderContextEvent` is dispatched for both sent mails and preview/simulation rendering so extensions can enrich mail template data through one hook.

### Google Ads Enhanced Conversions

A new Enhanced Conversions option was added to the Google Analytics integration. When enabled in the sales channel analytics settings, the checkout finish page sends the SHA256-hashed customer email address via `gtag('set', 'user_data', ...)` to support Google Ads Enhanced Conversions. Email addresses are normalized according to Google's requirements before hashing.

A new `enhanced_conversions` boolean field was added to `SalesChannelAnalyticsDefinition` and `SalesChannelAnalyticsEntity`.

New extensible Twig block `page_checkout_finish_enhanced_conversions` has been added to `finish-details.html.twig`.

### Country state field visibility in address forms

Storefront address forms now respect the country `displayStateInRegistration` setting.
When disabled, the country state field is hidden unless `forceStateInRegistration` is enabled, in which case the required state field is still shown.
During the update, `displayStateInRegistration` is activated for every country that has at least one configured state/region.
This keeps existing storefront address forms showing their state selector until the setting is disabled explicitly.

### Checkout gateway blocked method fallback

Storefront checkout cart and confirm page loading now resolves payment and shipping methods blocked by the checkout gateway before rendering the page.
The fallback method is selected from the checkout gateway response, preferring the sales-channel default method when available and otherwise using the first available method declared by the gateway.

### Customer address fields are trimmed on new writes

Customer address fields submitted through storefront registration and address updates are now trimmed before they are written.
This prevents leading or trailing whitespace from being stored in standard address fields such as first name, last name, street, city, and zipcode.

Existing customer address records are not changed.

### New `contentSelector` option for the `AlertAriaPlugin`

The `AlertAriaPlugin` now supports a `contentSelector` option to define the content element inside the `aria-live` region that is toggled to trigger the screenreader.
It defaults to `.alert-content-container`. Override it when applying the plugin to custom markup that is not based on the alert template:

```twig
<div class="cart-live-update visually-hidden"
     role="status"
     aria-live="polite"
     data-alert-aria="true"
     data-alert-aria-options='{{ { contentSelector: ".cart-live-update-content" }|json_encode }}'>
    <div class="cart-live-update-content">
        {# ... content that should be announced ... #}
    </div>
</div>
```

## API

### Plain JSON API includes preserve extension wrappers

The Admin API plain JSON encoder now keeps extension association fields inside the `extensions` object when they are selected through `includes`.
For example, including an extension association such as `toOne` on an entity returns `extensions.toOne` instead of promoting `toOne` to the top-level response.
Nested extension entities also respect their own include definitions, so API clients can filter extension payload fields consistently.

### Number range previews can target a concrete number range

The Admin API now supports previewing a persisted number range by id via `/api/_action/number-range/{numberRangeId}/preview-pattern`.
Use this route when editing an existing number range, because it reads the state for the concrete `number_range.id`.

The previous type-based preview route `/api/_action/number-range/preview-pattern/{type}` remains available in 6.7 for backwards compatibility, but is deprecated and will be removed in 6.8.
It can only resolve global number ranges and therefore does not support non-global number range state.
The allocation route `/api/_action/number-range/reserve/{type}` is unchanged.

### Empty `sw-*` id headers are treated as unset

Admin API and Store API requests now treat empty ID headers such as `sw-language-id`, `sw-currency-id`, `sw-app-integration-id`, and `sw-app-user-id` the same as missing headers.
Empty values fall back to the default request context instead of being forwarded as invalid UUIDs.
Whitespace-only values are still rejected as malformed IDs.

For cache efficiency, clients should consistently either omit `sw-language-id` and `sw-currency-id` or send them empty when they intentionally want default context resolution, because these headers can participate in reverse-proxy cache keys.

### Administration users receive default runtime privileges

Authenticated Administration users now receive the default privileges required by global Admin helpers: `language:read`, `locale:read`, `message_queue_stats:read`, `log_entry:create`, `currency:read`, and `country:read`.
The Administration role editor also adds these privileges to newly generated role permission sets.

## Core

### Rule Builder: new "Quantity per item" condition

A new line item rule condition `LineItemPerItemQuantityRule` (`cartLineItemPerItemQuantity`) was added. It matches the cart against the quantity of each individual line item, without selecting a specific product.
### Storefront snippets of self-managed apps are loaded

Storefront snippet files (`Resources/snippet/*.json`) shipped by self-managed apps (services) are now loaded.
Previously, the snippet loader resolved app snippets only from the local app directory, which self-managed apps do not have, so their storefront snippets were silently ignored.
The snippet files are now resolved through the app source system, the same way assets, scripts, and admin snippets of self-managed apps already are.
Service developers no longer need to work around missing storefront translations; the same app zip now behaves identically whether installed as a regular app or as a service.
### Deprecation of `shopware.cache.cache_compression` and `shopware.cache.cache_compression_method` config options

The `shopware.cache.cache_compression` and `shopware.cache.cache_compression_method` configuration options are deprecated and will be removed in v6.8.0.0. Please use the new `shopware.cache.compress` and `shopware.cache.compression_method` options instead.

#### Before

```yaml
shopware:
    cache:
        cache_compression: true
        cache_compression_method: 'gzip'
```

#### After

```yaml
shopware:
    cache:
        compress: true
        compression_method: 'gzip'
```

### Stored mail template type data deprecated

The persisted `mail_template_type.template_data` column is deprecated and will be removed in Shopware 6.8.
It was only used as stored preview data and is no longer needed after the mail template preview refactoring.

Use explicit `templateData` in the mail preview and send APIs, or generated data from the simulate endpoint, instead.
The mail API request payloads `templateData` and `mailTemplateData` remain supported and are not part of this deprecation.

### Pluggable thumbnail image processor

The thumbnail generation pipeline now uses a `ThumbnailProcessorInterface` instead of a hardwired GD implementation.
Two processors ship out of the box:

- `GdImageThumbnailProcessor` — uses the PHP GD extension and is the default.
- `ImagickThumbnailProcessor` — uses the PHP Imagick extension, if installed.

Switch between them in `config/packages/shopware.yaml`:

    shopware:
      media:
        thumbnail_processor: imagick   # or "gd" (default)

Both processors work with the new `ThumbnailImage` DTO (`Shopware\Core\Content\Media\Thumbnail\DTO\ThumbnailImage`), which is a thin wrapper carrying the underlying image resource.
`ThumbnailService` only ever deals with `ThumbnailImage` objects and is fully agnostic of the concrete library.

### Number range value generator interface deprecated

`NumberRangeValueGeneratorInterface` is deprecated in favor of `AbstractNumberRangeValueGenerator`.
Custom number range value generator implementations and decorators should extend the abstract class instead.
Implement `previewPatternByNumberRangeId()` for persisted number-range previews and continue using `getValue()` for actual number allocation.

The type-based `previewPattern()` method remains available for backwards compatibility in 6.7, but is deprecated and will be removed in 6.8.
Use `previewPatternByNumberRangeId()` when previewing or editing an existing number range.

### Orders no longer break on missing rule conditions in price definitions

If an order's `AbsolutePriceDefinition`, `CurrencyPriceDefinition`, or `PercentagePriceDefinition` references a rule condition that is no longer registered (e.g. a plugin contributing it has been uninstalled), `PriceDefinitionFieldSerializer` no longer throws `ConditionTypeNotFound`/`InvalidConditionException`. Such conditions are substituted with a new internal `UnknownConditionRule` whose `match()` always returns `false`.

The original rule payload is preserved verbatim on reads, order versioning and normal saves, so the order stays fully accessible and editable in the Administration and is restored automatically once the contributing plugin is reinstalled. Recalculation also succeeds, but because it recomputes the cart, a discount whose missing condition no longer matches any line item is removed (fail-closed) rather than preserved — so an order that is recalculated and saved may lose that discount line. When that happens, the recalculation result now contains a `promotion-discount-unknown-condition` warning (`PromotionDiscountUnknownConditionError`, shown as a notification in the Administration order detail page), so the discount is not removed silently.

Note that `match()` returning `false` only yields a fail-closed result for a standalone condition or inside an `AndRule`. Inside an `OrRule`/`XorRule` the surrounding container can still match through its other branches, and inside a `NotRule` the result is inverted to always-match.

Note for API consumers: writes to price-definition fields that reference an unregistered rule condition are now accepted instead of rejected, so order versioning and saves keep working. A mistyped condition name in an Admin API write is therefore no longer reported as a validation error — the condition is stored as-is and will simply never match.

### Elasticsearch: Dedicated `completion` field for admin-search autocomplete

Admin-search autocomplete now flows through a new `completion` field (ngram-indexed, populated with name-shaped values per entity). The ngram subfield has been dropped from `text`/`textBoosted` so identifiers (EAN, productNumber, orderNumber, etc.) no longer feed ngram scoring — fixing a regression where a full GTIN search could be outranked by unrelated products with overlapping digit substrings.

Run `bin/console es:admin:index` after deploying. Identifier search works immediately on the old index; substring autocomplete is degraded to prefix-only until the reindex completes.

### Type-safe subscription helpers on `Extension`

`Shopware\Core\Framework\Extensions\Extension` now exposes three static helpers — `onPre()`, `onPost()`, and `onError()` — that return the dispatched event name for the corresponding phase. Extensions need `::NAME` constant for the methods to work.

Use them in `getSubscribedEvents()` instead of string concatenation or `ExtensionDispatcher::pre/post/error()`:

```php
public static function getSubscribedEvents(): array
{
    return [
        ResolveListingExtension::onPre()  => 'method1',
        ResolveListingExtension::onPost() => 'method2',
        ResolveListingExtension::onError() => 'method3',
    ];
}
```

Dispatchers and subscribers no longer have to concatenate event-name strings - it gives type safety, IDE autocomplete, and rename-refactor support. Also subscribers don't have to depend on `ExtensionDispatcher`.

The previous styles — `MyExtension::NAME . '.post'` and `ExtensionDispatcher::post(MyExtension::NAME)` — continue to work and are not deprecated. No migration is required.

### Telemetry metrics evolution

The telemetry metrics abstraction behind the `TELEMETRY_METRICS` feature flag received several improvements ahead of stabilization in 6.8.
See [ADR 2026-04-23](./adr/2026-04-23-telemetry-v2-metrics-evolution.md) for the full reasoning.

- **Breaking**: `MetricTransportInterface` now requires a `flush()` method. Implement it as a no-op if the transport does not need lifecycle management. A new `TelemetryFlushListener` calls `flush()` on `kernel.terminate`, `console.terminate`, and (throttled) on `WorkerRunningEvent` so emissions from long-running workers reach the backend.
- Global kill-switch `shopware.telemetry.metrics.enabled` disables emission and removes services tagged `shopware.telemetry.subscriber` / `shopware.telemetry.periodic_metric_collector` via a compiler pass — zero overhead when off.
- Per-label validation policies: each label in a metric definition must declare either `allowed_values` or `policy: open`. Unknown values are handled per `policy` (`replace` / `discard` / `open`), with type-aware defaults (additive types replace, gauges discard). Unknown label names throw in dev/test and log at error level in production; replacements log at notice level in dev/test so typos like `GETT` vs `GET` surface during development.
- `PeriodicMetricCollectorInterface`: tag a service with `shopware.telemetry.periodic_metric_collector` to have its metrics collected by the `telemetry.collect_periodic_metrics` scheduled task (default 5 minutes, tunable via the standard scheduled-task administration). Useful for expensive aggregations and info metrics.
- New `Telemetry` facade: inject `Telemetry` to call `emit(ConfiguredMetric)` and `instrument(callback, DurationMetric?, Span?)` for combined duration metrics and profiler spans through a single entry point.
- Config cleanup: `allow_unknown_labels`, `allow_unknown_label_values`, and `enable_internal_metrics` are deprecated (superseded by per-label policies and per-metric `enabled`).

### Auto-resend double opt-in confirmation email on failed login

When a customer with an unconfirmed double opt-in account tries to log in, Shopware now automatically resends the confirmation email if the original was sent more than a configurable interval ago.

The interval is controlled by the new system config setting `core.loginRegistration.doubleOptInResendInterval` (default: `24` hours). Setting it to `0` disables the auto-resend entirely.

Successful password recovery now also confirms an unconfirmed double opt-in customer account, because completing the recovery flow proves access to the account email address.

### Standardized CLI JSON output flag

CLI commands now consistently use `--format json` to request JSON output. The previously used `--json` and `--output json` options are deprecated and will be removed in Shopware 6.8.0.0.

Affected commands:

- `bin/console user:list --json` → `bin/console user:list --format json`
- `bin/console app:list --json` → `bin/console app:list --format json`
- `bin/console plugin:list --json` → `bin/console plugin:list --format json`
- `bin/console dal:validate --json` → `bin/console dal:validate --format json`
- `bin/console sales-channel:list --output json` → `bin/console sales-channel:list --format json`

### New `sha256` Twig filter

A new `sha256` Twig filter is available alongside the existing `md5` filter. Both accept strings and arrays (arrays are JSON-encoded before hashing) and return the hex-encoded hash.

### Variants can now be searched by parent product name

The new `parent.name` search field allows variants to be found through their parent product name and ranked independently from the variant's own `name`.

The field is disabled by default. Enable `parent.name` in the product search configuration to make this behavior active and adjust its ranking there.
### Reduced product data in listings via description teaser

Product listings can now load a shortened, HTML-free excerpt of the product description instead of the full text, which significantly reduces database load, transfer size and memory usage for catalogs with large descriptions. Previously the complete description was loaded for every product box even though the storefront only displays a few clamped lines.

This reduced loading is **enabled for fresh installations** and **disabled for existing shops**, which keep the full listing loading on update and can opt in per sales channel via the new `core.listing.partialDataLoading` setting (Settings > Products). When enabled, the product listing route loads a curated, reduced field set covering the default product boxes; listing products are then partial entities. Only enable it if your theme and extensions work with the reduced product data in listings.

A new read-only, translatable `descriptionTeaser` field is available on `product` (and `product_translation`). It is derived from the description on write (HTML stripped, truncated to 512 characters) and exposed via the Store and Admin API. The stripping is configurable through the `html_sanitizer` field set `product_translation.descriptionTeaser`. Existing products are backfilled asynchronously: the migration schedules the `product.description_teaser.indexer`, which runs over the message queue after the update (or manually via `bin/console dal:refresh:index`).

## Administration

### Cache-relevant extension configuration fields

As a follow-up to [Reduced HTTP cache invalidation on system config changes](#reduced-http-cache-invalidation-on-system-config-changes), plugin and app `Resources/config/config.xml` files can now mark fields that affect cached storefront output with the `cache-relevant="true"` attribute on `<input-field>` or `<component>`.

When a marked field is changed in the Administration system config renderer, the save request explicitly sends `silent=false`, so HTTP cache entries tagged with `system.config-{salesChannelId}` are invalidated. Unmarked fields keep the default system config write behavior.

### Storefront icon cache and speculation rules can be configured per sales channel

The Storefront settings Administration page now allows the icon cache and speculation rules settings (`core.storefrontSettings.iconCache` and `core.storefrontSettings.speculationRules`) to be configured per sales channel.
`core.storefrontSettings.asyncThemeCompilation` remains a global setting and was moved into a separate Theme configuration card.

The storefront runtime now resolves the icon cache setting with the active sales channel id, matching the sales-channel-aware speculation rules lookup.
The old `sw_settings_storefront_smtp_settings` block is deprecated and will be removed in v6.8.0.

### Analytics settings split into Configuration and Tracking cards

The analytics settings view in `sw-sales-channel-detail-analytics` was split into two cards: Configuration (general settings like tracking ID, active state, anonymize IP) and Tracking (order tracking, offcanvas cart tracking, enhanced conversions).

New extensible Twig blocks `sw_sales_channel_detail_analytics_configuration`, `sw_sales_channel_detail_analytics_tracking`, `sw_sales_channel_detail_analytics_tracking_description`, and `sw_sales_channel_detail_analytics_fields_enhanced_conversions` have been added.

### Rule Builder cart total condition labels adjusted

Rule Builder cart total condition labels now describe more clearly which cart value they evaluate. The internal condition names remain unchanged for backwards compatibility.

| Internal name | EN old -> new | DE old -> new | What it checks |
| --- | --- | --- | --- |
| `cartCartAmount` | Grand total -> Cart total (incl. shipping) | Gesamtsumme -> Warenkorb-Gesamtsumme (inkl. Versand) | `cart.price.totalPrice` - final cart price incl. shipping/tax/discounts |
| `cartPositionPrice` | Total -> Cart sum of items (excl. shipping) | Summe -> Summe der Positionen (ohne Versand) | `cart.price.positionPrice` - sum of all items, no shipping |
| `cartGoodsPrice` | Subtotal of all items -> Subtotal of goods (excl. discounts/fees) | Zwischensumme aller Positionen -> Zwischensumme der Warenpositionen (ohne Rabatte/Zuschläge) | Sum of goods item prices, excl. shipping/promotions |
| `cartTotalPurchasePrice` | Total of all purchase prices -> Sum of purchase prices | Summe aller Einkaufspreise -> Summe der Einkaufspreise | Sum of purchase/cost prices across all items |
| `cartLineItemTotalPrice` | Item subtotal -> Item total (qty × price) | Positionszwischensumme -> Positionssumme (Menge × Preis) | One item's total, quantity multiplied by unit price |
| `cartLineItemGoodsTotal` | Total quantity of all products -> Total product quantity (units) | Gesamtanzahl aller Produkte -> Gesamtmenge der Produkte (Stück) | Total unit count of goods in the cart |
| `cartGoodsCount` | Total quantity of distinct products -> Number of distinct products | Gesamtanzahl unterschiedlicher Produkte -> Anzahl unterschiedlicher Produkte | Number of distinct products in the cart |

### Rule Builder quantity condition labels disambiguated

| Internal name | EN old -> new | DE old -> new | What it checks |
| --- | --- | --- | --- |
| `cartLineItemWithQuantity` | Item quantity -> Item with quantity | Positionsanzahl -> Position mit Menge | A specific selected product's quantity |
| `cartLineItemsInCartCount` | Quantity of distinct items -> Number of distinct items | Anzahl unterschiedlicher Positionen (unchanged) | Number of distinct line items (all types) |
| `promotionsInCartCount` | Quantity of discounts -> Number of discounts | Anzahl der Rabatte (unchanged) | Number of discount line items |

### `sw-data-grid` column labels fall back to the default locale

Column headers and the column visibility settings in `sw-data-grid` now resolve their labels against the configured i18n fallback locale when the snippet is missing in the current locale, instead of rendering the raw snippet key. This matches the behavior users expect when a translation is only available in English.

### Reworked timeframe options in `sw-date-filter`

The order date filter dropdown now offers a 15-entry list, in display order:

1. Today
2. Yesterday
3. Current week
4. Last 7 days
5. Previous week
6. Current month
7. Last 30 days
8. Previous month
9. Current quarter
10. Previous quarter
11. Last 3 months
12. Last 6 months
13. Last 12 months
14. Current year
15. Previous year

"Current ..." entries span the start of the period through today (e.g., current quarter = first day of the quarter through today). "Previous ..." entries cover the full prior period (e.g., previous quarter = the three months before this one). "Last N days/months" remain rolling windows ending today, with calendar-month math (and last-day-of-month clamping) for the months variants so May 31 - 3 months lands on Feb 29 (leap) or Feb 28 (non-leap) rather than rolling forward to March 3.

The previous rolling `lastDay` (-1) and `lastYear` (-365) entries are no longer in the dropdown, but saved filter states keep working: the component now aliases `-1` to `yesterday` and `-365` to `last12Months` on both hydration and programmatic selection. The persisted `from`/`to` are preserved so existing filters continue to resolve the same data, while the dropdown label catches up to the new vocabulary. All boundaries continue to be normalized to the user's timezone.

### Support test file splitting

Administration Jest tests can now be split into multiple files using `*.spec/` directories.
ESLint now warns for Administration test files with 500 lines or more and errors for test files with 1000 lines or more.

### App action button icons are aligned in Administration context menus

App action buttons that use an app manifest icon now render the icon at the normal context-menu size and align it on the same row as the action label.
Previously, the app logo could render oversized or stacked above the action text in Administration action menus, for example on order detail pages.

### Product variants are easier to distinguish in `sw-entity-multi-id-select`

`sw-entity-multi-id-select` now displays product variant option details for product repositories in the selected labels and dropdown results.
This helps extensions and plugin configuration UIs that let merchants select multiple products, because variants with inherited product names no longer appear as identical entries.

### Outside clicks in dropdowns are identified correctly

Administration dropdowns now identify outside clicks correctly when the browser reports a click target outside the dropdown even though the pointer is still over the dropdown.

### Resolving download errors by renaming media

When merchants rename a media file, its URL automatically updates so they can download it without issues.

## App System

### [Opt-in] Webhook delivery rework

Webhook delivery moves to a new dedicated `webhook` Messenger transport, rolled out behind the `WEBHOOKS_REWORK` feature flag.
When the flag is disabled (which is the default), the `webhook` transport forwards to `async` and Messenger owns retries.
When the flag is enabled, every webhook is persisted to a database-backed outbox before the first HTTP attempt, and Shopware controls when and how often each delivery is retried.

With the flag enabled:

- **Failed deliveries are retried for up to four hours.** Failures back off on a fixed `5s → 30s → 5min → 30min → 4h` schedule, so a brief DNS outage or upstream restart does not exhaust retries before the endpoint recovers.
- **Synchronous deliveries are audited.** Deliveries that bypass the queue — those triggered by the admin worker or by forced-sync app lifecycle calls — produce the same audit row as async deliveries, so failures on those paths are inspectable in the database and via the Admin API.
- **In-flight deliveries survive worker crashes.** If a worker dies while sending a webhook, the next worker picks up the in-flight delivery and retries it.
- **Identity headers on every HTTP POST.** Every request carries `X-Shopware-Event-Id`, `X-Shopware-Sequence`, and `X-Shopware-Attempt` headers plus a `source.sequence` field in the body. Consumers can use them to deduplicate retries and reorder events independent of HTTP arrival order. The same headers ship for every webhook, regardless of how it is delivered.

Enabling the flag requires configuration changes — the worker consume command must list the new `webhook` transport, and `shopware.admin_worker.transports` may need updating if it was overridden. Rolling the flag back off also has its own steps. See `UPGRADE-6.7.md` for the full procedure.

Tracked in [shopware/shopware#16560](https://github.com/shopware/shopware/issues/16560).

## Hosting & Configuration

### Google Storage supports application default credentials

Google Storage filesystem configurations can now omit `keyFile` and `keyFilePath`.
When neither option is configured, Shopware lets the Google Cloud PHP SDK resolve credentials through [Application Default Credentials](https://docs.cloud.google.com/docs/authentication/application-default-credentials), such as `GOOGLE_APPLICATION_CREDENTIALS`, local ADC files, or attached service accounts in Google Cloud environments.
See Google's [PHP client authentication guide](https://docs.cloud.google.com/php/docs/reference/help/authentication) for the PHP library lookup behavior.

## Critical Fixes

### Admin worker no longer blocks same-session API requests on PHP session locks

Fixed a session-locking issue that could slow down concurrent Administration requests when the browser-based admin worker is used together with native PHP file session storage.
In that setup, an admin queue worker request could initialize the current PHP session while consuming messages and keep the session file locked until the long-poll request returned.
Concurrent Admin API requests from the same browser session, for example sync or save requests, no longer wait for that admin worker session lock.

# 6.7.11.0

## Features

### [Experimental] MCP Server for AI tool integration

Shopware now includes an experimental MCP (Model Context Protocol) server that lets AI clients like Claude Desktop or Cursor interact with your Shopware instance through a standardized protocol.

The server exposes the full MCP capability set:
- **Tools** for entity management (search, read, create, update, delete), system configuration, state machine transitions, cache management, and storefront product search with sales channel context.
- **Prompts** that give the AI client context about Shopware's data model, criteria format, and best practices.
- **Resources** that expose static reference data (entity list, sales channels, state machines, business events, flow actions) as readable URIs.

All operations respect the authenticated user's ACL permissions and integrate with the Admin API authentication. Integration credentials can be passed directly via `sw-access-key` and `sw-secret-access-key` headers. No separate OAuth token exchange is required. Per-integration allowlists are configurable under Settings -> Integrations to limit which tools, prompts, and resources a given client can see.

To enable this feature, set the `MCP_SERVER` feature flag to `true`. The MCP endpoint is available at `/api/_mcp` and uses the Streamable HTTP transport. Plugins register additional MCP capabilities by tagging services with `mcp.tool`, `mcp.prompt`, or `mcp.resource`. Apps can declare them in their app manifest.

A `debug:mcp` CLI command is available to list all registered MCP tools, prompts, and resources.

## API

### New foreign key resolvers for the Sync API

The Sync API now ships seven additional foreign key resolvers, allowing payloads to reference entities by stable human-readable keys instead of UUIDs:

* `currency.iso_code` — resolves a `currency` by its `isoCode` (e.g. `EUR`).
* `locale.code` — resolves a `locale` by its `code` (e.g. `en-GB`). The `en_GB` underscore variant is also accepted.
* `payment_method.technical_name` — resolves a `payment_method` by its `technicalName`.
* `shipping_method.technical_name` — resolves a `shipping_method` by its `technicalName`.
* `document_type.technical_name` — resolves a `document_type` by its `technicalName`.
* `salutation.salutation_key` — resolves a `salutation` by its `salutationKey` (e.g. `mr`).
* `tax.tax_rate` — resolves a `tax` by its `taxRate`. Because `tax_rate` is not unique, the resolver only resolves a value when exactly one tax row matches the given rate; ambiguous rates are left unresolved (combine with `nullOnMissing: true` if appropriate).

Use these inside a Sync payload anywhere a UUID is expected, e.g. `{"currencyId": {"resolver": "currency.iso_code", "value": "EUR"}}`.

### Mail template preview and send routes support richer rendering context

The mail template Admin API now exposes dedicated preview and send routes:

- `/api/_action/mail-template/simulate`
- `/api/_action/mail-template/preview`
- `/api/_action/mail-template/get-data-and-send`
- `/api/_action/mail-template/available-variables`

The preview routes support sales-channel-aware rendering.
`/api/_action/mail-template/preview` accepts `salesChannelId`, `includeHeaderFooter`, and `strictRendering`, and `/api/_action/mail-template/simulate` accepts `salesChannelId` and `strictRendering`.
This allows Administration extensions and custom tooling to preview the final mail output, including sales-channel-specific headers and footers, against the same rendering context used for sending.

`/api/_action/mail-template/get-data-and-send` lets callers resolve a persisted mail template together with entity-based template data before sending.
`/api/_action/mail-template/available-variables` exposes the variable tree for a business event so extensions can build mail-template editing and preview tooling without hardcoding the available data shape.

The `/api/_action/mail-template/send` payload now also has a first-class `extensions` bag for custom mail data.
Arbitrary unknown top-level keys are still forwarded for backwards compatibility in 6.7, but they are deprecated and will stop being forwarded in Shopware 6.8.

## Core

### Backward compatible invalid locales

Added and deprecated `BackwardCompatibleNumberFormatter` to temporarily allow invalid locale strings without throwing exceptions in PHP >=8.4. It will be removed in Shopware 6.8.

### Configurable order deep link expiry

The number of days an order can be accessed via deep link is now configurable via `shopware.yaml`:

    shopware:
      order:
        deep_link:
          expire_days: 30

### Technical media associations can be ignored by `media:delete-unused`

Plugins can now mark technical `media` associations with the new DAL flag `IgnoreInUnusedMediaSearch`.
This prevents `media:delete-unused` from treating metadata-only extensions as real media usage and helps avoid false negatives when removing unused files.
Third-party developers should add this flag to media associations that store technical metadata but do not represent an actual assignment of the media file.

### State machine transitions are locked per entity

State machine transitions now acquire a short-lived lock per entity and context version while the current state is read and the transition history is written.
This prevents concurrent calls to `StateMachineRegistry::transition()` from creating duplicate history entries for the same entity transition.
Extensions that use the registry automatically benefit from the lock; direct SQL or DBAL writes to state fields remain outside this protection.

### Deprecation of RegisterScheduleTaskMessage

The `RegisterScheduleTaskMessage` class and the accompanying message handler `RegisterScheduledTaskHandler` is deprecated and will be removed in Shopware 6.8.0.0, as the message wasn't dispatched anymore.
If you dispatched that message manually, you should call the `TaskScheduler::registerTask()` method directly instead.

### Plugin snippet files are no longer silently dropped when any translation is installed

Plugin snippet files (`.json` files shipped in `Resources/snippet/`) were being skipped for **all** locales as soon as a core translation for **any single locale** was installed via the translation installer.
Installing `pl-PL` for one plugin would cause `de-DE`, `en-GB`, and every other locale to lose that plugin's translations entirely, even though no core translation for those locales existed.

The guard in `SnippetFileLoader` now checks whether a core translation exists for the **specific locale** being loaded, not for the plugin as a whole.

If you have decorated `AbstractTranslationLoader`, override the new `pluginTranslationExistsForLocale(Plugin $plugin, string $locale): bool` method to provide locale-aware behaviour.
The old `pluginTranslationExists(Plugin $plugin)` is deprecated and will be removed in v6.8.0.

### Composer-managed plugins in `TestBootstrapper::addActivePlugins()`

`TestBootstrapper::addActivePlugins()` can now be used with Composer-managed plugins installed below `vendor/`.
Plugins no longer need to be copied into `custom/plugins` or `custom/static-plugins` just to be installed and activated during test bootstrap.
When `TestBootstrapper::getPluginPath()` or `getClassLoader()` is used without bootstrapping the full application, local plugins below `custom/plugins` and `custom/static-plugins` are still resolved from the filesystem.
This keeps static analysis and other tooling that only needs plugin paths or `autoload-dev` registration working without a database-backed kernel.

### Requirement-aware plugin installation order

`plugin:install` now orders the selected plugins by their Composer plugin requirements before installation.
When one selected plugin requires another selected plugin package, the required plugin is installed first.
This ordering only applies to plugins that are known before the command starts.
The command does not reload Composer's autoloader while it is running.
If installing one plugin also installs new PHP packages, plugins installed afterwards in the same command cannot use those packages yet.
Run those installs in separate CLI calls when a plugin depends on code that another plugin adds through Composer during installation.

### Listing configured translations via `translation:list`

A new `translation:list` console command prints every locale configured for `translation:install` / `translation:update`, including its localized name, English name, and the timestamp of the last installed Crowdin snapshot.
`translation:install` without `--all` or `--locales` now drops into an interactive multi-select prompt with autocompletion over the available locale codes, instead of throwing an exception.

### Support for pseudo-locales in `translation:install`

The new `SnippetPatterns::ALLOWED_PSEUDO_LOCALES` and `SnippetPatterns::PSEUDO_LOCALE_TERRITORY` constants register Crowdin pseudo-languages (e.g. `ach-UG`) as valid translation targets for in-context proofreading and translatability audits.
Pseudo-locales bypass Symfony Intl validation in `Language::validateLocale` and `TranslationLoader::getLocalePath`, and a missing `locale` entity is auto-created on install with a display name from the constant map and a fixed `Pseudo Language` territory.

## Administration

### Block renaming

Due to misleading block names, the following blocks have been deprecated and will be removed in v6.8.0. Use the respective replacements instead:

* `sw_settings_listing_option_base_smart_content` -> `sw_settings_listing_option_base_content`
* `sw_settings_listing_option_base_smart_content_general_info` -> `sw_settings_listing_option_base_content_general_info`
* `sw_settings_listing_option_base_smart_bar_actions_grid` -> `sw_settings_listing_option_base_content_criteria_grid`
* `sw_settings_listing_option_base_smart_bar_actions_grid_delete_modal` -> `sw_settings_listing_option_base_content_delete_modal`

### Mail template preview is now sales-channel-aware and uses isolated HTML rendering

The mail template detail page can now preview mails with the selected sales channel and its configured mail header and footer.
This helps developers and merchants validate the final rendered output more accurately, especially for document mails and installations with channel-specific branding.

The HTML preview is now rendered in a sandboxed iframe instead of being injected directly into the Administration DOM.
This keeps the preview close to the actual mail output while reducing the risk of script execution from rendered template content.

### Custom fields respect read-only permissions in Administration detail views

Custom fields on category, landing page, sales channel, customer address, and order address detail views are now disabled when the current user only has read permissions.

### Fixed "Last Quarter" timeframe returning the wrong year in `sw-date-filter`

Selecting the "Last Quarter" timeframe in any listing's date filter (orders, documents, customers, etc.) between January and March now produces a three-month range in the previous year instead of a ~15-month range that spanned both years.
The end boundary is now derived from the quarter's start year rather than the current year.

### Reworked timeframe options in `sw-date-filter`

The order date filter dropdown now offers a 15-entry list, in display order:

1. Today
2. Yesterday
3. Current week
4. Last 7 days
5. Previous week
6. Current month
7. Last 30 days
8. Previous month
9. Current quarter
10. Previous quarter
11. Last 3 months
12. Last 6 months
13. Last 12 months
14. Current year
15. Previous year

"Current ..." entries span the start of the period through today (e.g., current quarter = first day of the quarter through today). "Previous ..." entries cover the full prior period (e.g., previous quarter = the three months before this one). "Last N days/months" remain rolling windows ending today, with calendar-month math (and last-day-of-month clamping) for the months variants so May 31 - 3 months lands on Feb 29 (leap) or Feb 28 (non-leap) rather than rolling forward to March 3.

The previous rolling `lastDay` (-1) and `lastYear` (-365) entries are no longer in the dropdown, but saved filter states keep working: the component now aliases `-1` to `yesterday` and `-365` to `last12Months` on both hydration and programmatic selection. The persisted `from`/`to` are preserved so existing filters continue to resolve the same data, while the dropdown label catches up to the new vocabulary. All boundaries continue to be normalized to the user's timezone.

### Admin menu flyout no longer overflows the viewport

When the sidebar is collapsed, hovering a menu entry near the bottom of the sidebar could cause the flyout submenu to extend beyond the viewport, making lower entries inaccessible.
The flyout now calculates a dynamic `max-height` from the remaining viewport space and scrolls vertically when its content exceeds that limit.

### Meteor Component Library updated to 4.28.6

The Administration now uses Meteor Component Library `4.28.6`.
With this update, disabled Meteor switch fields in system configuration can now unlink inherited sales channel values.
Previously, the switch field itself was disabled as expected, but its inheritance control was disabled as well, preventing merchants from overriding inherited values for that sales channel.

### Administration sidebar off-canvas closes on mobile navigation

The Administration sidebar off-canvas now closes reliably on very small viewports after selecting a navigation entry, clicking outside the sidebar, or changing routes.

### Fix theme manager inheritance for boolean fields

Switch and checkbox fields in theme configuration now render and handle inheritance consistently. Before they wouldn't have shown the inheritance switch.
Also the checkbox field is now positionally aligned with the other components.

## Storefront

### New Component System

We introduced a new component system to the Storefront, which makes it easier to create reusable templates. It is one foundation of a new content system, which will be released at a later stage, but components can also be used anywhere in existing templates. The component system is based on [Twig UX components](https://symfony.com/bundles/ux-twig-component/current/index.html), plus some additional features like SCSS and JS handling for your components.

To dive into the full possibilities, please refer to the [official documentation](https://developer.shopware.com/docs/concepts/framework/storefront-components.html).

### New Dev-Server for development based on Vite

With the new component system we introduced a separate build process based on Vite. With that there is also a new dev-server feature available that also supports usual theme file updates for SCSS and JS. It offers a better developer experience, because it does not need a proxy. You can simply work in your normal Storefront while the dev-server is active.

```
composer storefront:dev-server
```

The current `composer watch:storefront` command is deprecated for the next major version. Use the new dev-server instead.

### Theme config available as native CSS custom properties

With the new content system we want to move away from the PHP-based SCSS compilation. As a first step, we made the theme configuration available as native CSS custom properties. You can start using them instead of SCSS variables for colors and other visual settings in CSS. The CSS custom properties are available under the same name as the SCSS variables.

**Example**
```CSS
.btn-primary {
    background: var(--sw-color-brand-primary);
}
```

Available are all config fields that does not have set `scss: false` in the theme configuration.

### Single file references in theme.json

The `theme.json` file now supports single file references, allowing you to include individual files from other bundles rather than pulling in an entire theme or plugin. This gives themes fine-grained control over exactly which files are compiled.

This is available for both `style` and `script` entries:

**Bundle-relative references** — Include a single specific file from another bundle or theme using `@BundleName/path/to/file`:

```json
{
  "style": [
    "@MyTheme/app/storefront/src/scss/overrides.scss",
    "@MyTheme"
  ],
  "script": [
    "@MyPlugin/app/storefront/dist/storefront/my-plugin.js",
    "@Plugins"
  ]
}
```

### New global JavaScript event system

With the new component system we also start to improve the general possibilities in the Storefront. One of these improvements is a new global event system that is available via a new central `Shopware` object. This system is easier to use than the instance scoped events from the current JS plugin system. The event system is based on the native Node [event emitter](https://nodejs.org/en/learn/asynchronous-work/the-nodejs-event-emitter) and can be used in a similar way. You will find some additional features, like interceptable events which can be used to hook into certain methods, like changing request parameters before they get send. We want to offer this as a new extension system, especially for the new component system.

```JavaScript
window.Shopware.emit('Filter:Change', { foo: 'bar' });
```

```JavaScript
window.Shopware.on('Filter:Change', ({ foo }) => {
    // do something
});
```

For more detailed information, refer to the [documentation](./src/Storefront/Resources/app/storefront/src/component-system/README.md).

### New plugin manager function to call plugin methods

We added a new method to the Storefront plugin manager which allows to call a specific plugin method on all existing instances of that plugin.

```JavaScript
window.PluginManager.callPluginMethod(pluginName, methodName, ...args)
```

### Single-hit search redirect now matches EAN and manufacturer number

The storefront search already redirected to the product detail page when a search term exactly matched a product's number and produced a single result.
The same redirect now triggers when the term exactly matches the product's `ean` or `manufacturerNumber`.
The condition still requires exactly one matching product, so listings with multiple hits remain unaffected.

The set of fields that trigger the redirect is configurable via the `shopware.storefront.redirect_on_single_hit_fields` container parameter (defaults to `['productNumber', 'ean', 'manufacturerNumber']`).
Any string-valued property declared on `ProductEntity` may be configured — unknown or non-string properties are skipped.
Set the parameter to a narrower list (for example `['productNumber']`) to restore the previous behaviour.

## Hosting & Configuration

### Local filesystem permission enforcement can be disabled

Local filesystem adapters now support `config.enforce_file_permissions: false` to preserve existing file permissions after writes.
This is useful for installations that manage permissions outside Shopware, for example with ACLs or shared deployment users, where writes are allowed but `chmod()` calls should not be enforced by the application.

### Partial filesystem visibility overrides preserve adapter configuration

Partial filesystem visibility overrides now keep the previously configured adapter `type` and `config`.
Replacing the adapter `config` block still replaces it as a whole, so adapter-specific config from a previous definition is not mixed into the new adapter.

## Critical Fixes

### Transient Elasticsearch outages no longer break order placement

`ElasticsearchHelper::allowIndexing()` now catches transport-level exceptions thrown from `Client::ping()` (e.g. DNS failures, connection refused, timeouts) and routes them through `logAndThrowException()`.

Previously, a transient Elasticsearch / OpenSearch outage during checkout caused a `ConnectException` to bubble out of `ProductUpdater::update()` (triggered by `ProductStockAlteredEvent` after stock decrement), aborting the request after the order had already been written to the database. With `SHOPWARE_ES_THROW_EXCEPTION=0`, the indexing call is now logged at `critical` and skipped for that request; order placement completes normally. With `SHOPWARE_ES_THROW_EXCEPTION=1` (the default) behavior is unchanged — the exception is still re-thrown.

# 6.7.10.1

## Critical Fixes

### SVG uploads validate against a strict passive allowlist

SVG uploads in the media subsystem are now validated against a strict passive SVG allowlist before persistence.
Active content such as scripts, event handlers, processing instructions, external references, and URL-based references in attributes are rejected.

The default allowlist covers the W3C SVG2 presentation attribute set (https://www.w3.org/TR/SVG2/attindex.html#PresentationAttributes), ARIA accessibility attributes, the `lang` and `xml:lang` accessibility attributes, and the common safe structural elements `a`, `image`, `marker`, `metadata`, `switch`, `symbol`, and `view`. Anchor `href` / `xlink:href` references remain restricted to local document fragments (`#id`), so `javascript:`, `data:`, and remote URLs are rejected. Active content (scripts, event handlers, animations, foreign objects, processing instructions, DOCTYPEs, entities) and any external `url(...)` / `@import` references remain blocked regardless of the attribute that carries them.

The accepted SVG subset can be adjusted on installation level via `shopware.media.svg.allowed_elements`, `shopware.media.svg.allowed_attributes`, and `shopware.media.svg.allowed_reference_attributes` in `shopware.yaml`.

### `external-link` endpoint URL validation aligned with `upload-from-url`

The URL validation for the `external-link` endpoint is now in line with the existing validation in the `upload-from-url` flow.
The static `MediaUploadService::validateExternalUrl()` is deprecated in favour of the new `assertValidExternalUrl()` method on the service.
See `UPGRADE-6.8.md` for migration details.

# 6.7.10.0

## Features

### [Experimental] Agentic Commerce sales channel

A new "Agentic Commerce" sales channel type is available in this release.
The OpenAI Merchant Center integration is the first supported provider for AI-powered product feed exports.
The Administration includes dedicated views for configuration, product mapping, and usage insights.

### [Experimental] Breadcrumb category referrer

Shopware now supports building product breadcrumbs based on the referring category. When a customer lands on a product detail page from a category listing, the breadcrumb can dynamically reflect the category path they came from.

To enable this feature, set the `BREADCRUMB_REWORK` feature flag to `true` and activate the "Build breadcrumb based on referrer category" setting in Settings > Products.

## API

### Per-user and per-IP rate limiters for login and OAuth

The login and OAuth token endpoints now support optional per user (`login_user`, `oauth_user`) and per IP (`login_client`, `oauth_client`) rate limiters, in addition to the existing combined user and IP limiter.
These are optional and can be enabled via `shopware.api.rate_limiter` in `shopware.yaml`.

### Store API routes for shipping cost calculation

The Store API now provides dedicated shipping-cost endpoints for product and cart previews. This allows headless storefronts and integrations to fetch shipping prices and delivery dates for multiple shipping methods without changing the customer's persisted cart or selected shipping method.

For product previews, `/store-api/shipping-cost/product/{productId}` uses Shopware criteria parameters to select which shipping methods should be loaded for the calculation.
For cart previews, `/store-api/shipping-cost/cart` returns the shipping costs for the current cart across the available shipping methods.

The response contains the calculated shipping price, delivery date, and shipping method data for each result, which makes it easier to build shipping-method selectors or delivery previews in custom storefronts and apps.

### `Price` schemas now describe percentage and reference price fields

The generated Admin API and Store API `Price` schemas now include property descriptions for `percentage`, `listPrice`, `regulationPrice`, and their nested values.
This improves the generated OpenAPI and Stoplight documentation for integrations that inspect raw price payloads and need to distinguish between the current price, list price, discount percentage, and regulation price fields.

## Core

### Elasticsearch: Configurable minimum score threshold for search results

A new system configuration key `core.search.minScore` (float, default `0.0`, per sales channel) lets merchants drop low-relevance Elasticsearch hits. When the value is above `0.0`, it is applied as the native `min_score` parameter on the product term-search query.

The setting is most useful for cutting the long tail of fuzzy or ngram-only matches on single-token queries. There is no universally correct value — the effective BM25 score range depends on field weights, analyzer configuration, and catalog size — so start with a low threshold and increase it gradually while watching how noisy queries behave. Leave at `0.0` to disable.

Adjust via the System Config API using the key `core.search.minScore`.

### Elasticsearch: Disabled BM25 field-length normalization for structured search fields

Elasticsearch product search now uses a custom BM25 similarity with `b=0` (no field-length normalization) as the index default. This prevents short product names like "Sony TV" from ranking unfairly above descriptive ones like "Sony 65-inch 4K Ultra HD Smart OLED TV" when both match the same search terms.

Long-form text fields (`description`, `metaDescription`) retain the standard BM25 normalization (`b=0.75`) via the `sw_length_norm` similarity, since document length is a meaningful relevance signal for prose content.

Both similarities are configurable via `elasticsearch.similarity` in `elasticsearch.yaml`. This change requires a full reindex (`bin/console es:index`).

### Product `display_group` values use SHA-256

The `display_group` field on the `product` entity (available via the Admin API and Store API) is now computed with SHA-256 for variant listing instead of MD5.
Stored values are 64 hexadecimal characters instead of 32. The database column was widened to `VARCHAR(64)`.

A migration registers the product indexer so that only the variant listing updater (`product.variant-listing`, the step that maintains `display_group`) is queued.
That pass runs with the usual deferred indexing after an update or installation finishes, not inside the migration.
If your integration or plugin assumes a 32-character `display_group`, compares against previously stored MD5 values, or relies on custom SQL with the old column width, update it to accept 64-character hashes and the new column definition.

### "Find best variant setting" is now applied for storefront filtering

Users can now control which representative of variant products is shown in filtered listings via the Product settings "Preview best matching variant in search results and filtered listings".
### Deprecation of `permisionsLocked` property of `SalesChannelContext`

The `permisionsLocked` property of the `SalesChannelContext` is deprecated.
Use `permissionsLocked` property or the new `SalesChannelContext::isPermissionsLocked()` getter method instead.
### Elasticsearch: Extracted field query builders from TokenQueryBuilder

The `TokenQueryBuilder` has been refactored to use a decoration-based architecture for field query generation. A new `AbstractFieldQueryBuilder` abstract class serves as the public extension point, with internal implementations for:
- base field matching (`FieldQueryBuilder`)
- translated field handling (`TranslatedFieldQueryBuilder`)
- nested field wrapping (`NestedFieldQueryBuilder`)
- and explain metadata for preview mode(`ExplainFieldQueryBuilder`).

Additionally, `TokenQueryBuilder` now extends a new `AbstractTokenQueryBuilder` abstract class, enabling decoration of token-level query composition. The old `Shopware\Elasticsearch\TokenQueryBuilder` service ID is preserved as an alias for backward compatibility.

Plugins that need to customize Elasticsearch field query generation can now decorate either `Shopware\Elasticsearch\AbstractFieldQueryBuilder` or `Shopware\Elasticsearch\AbstractTokenQueryBuilder` instead of replacing the entire token query builder.

### Elasticsearch: Added configurable tie_breaker to dis_max queries

Elasticsearch `dis_max` queries now include a `tie_breaker` parameter at the field level, translated field level, and token combination level. Previously, `dis_max` only considered the single best-matching clause. With `tie_breaker`, scores from other matching clauses contribute partially to the overall score, improving ranking for documents that match across multiple fields or language variants.

The value is configurable via `elasticsearch.search.dismax_tie_breaker` in `elasticsearch.yaml` (default `0.2`).

### Salutation ordering

A new `position` column was added to the `salutation` entity so merchants can control the order in which salutations appear in forms (registration, address, checkout, and CMS forms).
Salutations are sorted ascending, meaning lower values appear first.

This replaces the previous alphabetical sorting.
Default salutations (`not_specified`, `mrs`, `mr`) are migrated automatically to positions `1`, `2`, and `3`.
Custom salutations keep the default value of `100` - review them in Administration → Settings → Shop → Salutations after upgrading and assign explicit positions, otherwise they will appear grouped together at the end.

### Deprecated non-used `MAIL_TEMPLATE_SALES_CHANNEL_*_EVENT` constants

Deprecated the constants `Shopware\Core\Content\MailTemplate\MAIL_TEMPLATE_SALES_CHANNEL_{WRITTEN,DELETED,LOADED,SEARCH_RESULT_LOADED,AGGREGATION_LOADED,ID_SEARCH_RESULT_LOADED}_EVENT` as the entity has been removed with Shopware 6.5 and the events were not fired anymore.

### JSONL product export format

Product exports now support `ProductExportEntity::FILE_FORMAT_JSONL` as a third file format.

### [Experimental] Agentic Commerce product export provider abstraction

The new `AbstractAgenticCommerceProductExportProvider` can be used to implement custom Agentic Commerce export providers.

## Administration

### SFC migration codemod for Administration components

A new developer tool is available to migrate Shopware Administration components from the legacy two-file format (`index.js` + `.html.twig`) to single-file components (`.vue` SFCs).

Run it via:

```bash
npm run codemod:sfc-migration -- <target-directory>        # dry-run preview
npm run codemod:sfc-migration -- --write <target-directory> # write .vue files
```

The codemod converts Options API to Composition API (`data` → `ref`, `computed`, `watch`, `methods`, lifecycle hooks), rewrites Twig block syntax to `<sw-block>` elements, and merges template + script into a single `.vue` file.
Components with `render()` functions are skipped; components using `mixins` or `Shopware.Component.extend()` receive a backoff to plain `<script>` so they can be migrated manually.

See `src/Administration/Resources/app/administration/scripts/codemods/sfc-migration/README.md` for full usage, flags, and known limitations.
### [Internal] Twig to Native Block Runtime Adapter
A runtime adapter has been added that bridges legacy Twig block overrides (`{% block %}` / `{% parent %}`) with the new native `<sw-block>` / `<sw-block-parent />` system. When core components migrate from `.html.twig` blocks to `<sw-block name="...">`, existing plugin overrides continue to work automatically. A deprecation warning is emitted to guide plugin developers toward the new native syntax.
### Fixed mixin-based route guards for lazy-loaded administration routes

Mixin-defined route guards such as `beforeRouteLeave` are now executed reliably for lazy-loaded Administration route components.
This fixes cases where cleanup logic in shared mixins, for example in listing pages, was skipped during navigation to detail pages.

### Re-render iframe integrations when location changes

Iframe-based Administration extensions now re-render correctly when their `locationId` changes.
This fixes stale iframe content when switching locations in Meteor Admin SDK integrations and also prevents unnecessary full-page reloads.

### Internal comments visible in the order list

The Administration order list now shows internal order comments via a dedicated tooltip icon.
This helps merchants spot internal notes directly from the list view without opening the order detail page.

### [Experimental] Agentic Commerce sales channel views and tracking entities

New Agentic Commerce sales channels types can be created.
These sales channels have dedicated configuration options in the administration for property mapping, and usage insights.
New entities for monitoring orders and customers for Agentic Commerce sales channels are included.

## Storefront

### Order cancellation only shown for open orders

The account order cancellation action is now only shown for orders in state `open`.
This prevents customers from being offered an invalid cancel action for completed orders.

### Earlier focus for cookie bar

To improve the accessibility of the cookie bar, it receives automatic focus when it is shown.
This improves discoverability for screenreader and keyboard users.
A new option `autoFocus` (default: `true`) was added to the `cookie-permission.html.twig` template and `CookiePermissionPlugin`.

In addition to this the cookie bar will be moved to the top of the body element.
* Deprecated block position of `base_cookie_permission` Cookie permission bar will be moved to top of the body element.

### Live purchase limits for closeout products on the product detail page

The buy-widget quantity selector now fetches live `minPurchase`, `purchaseSteps`, and `maxPurchase` values for closeout products (internally uses new Store API endpoint `GET /store-api/product/purchase-limit`) on first user interaction (focus or click).
This ensures the selector reflects actual stock even when the PDP HTML is served from HTTP cache.

The fetch is triggered by the `QuantitySelectorPlugin` when a `purchaseLimitUrl` option is set on the quantity selector element.
This is injected via `data-quantity-selector-options` by `buy-widget-form.html.twig` for closeout products.
If you override `buy_widget_buy_container` or related blocks in `buy-widget-form.html.twig`,
preserve the `data-quantity-selector-options` attribute with a `purchaseLimitUrl` key and the `js-quantity-stock-adjusted-template` `<template>` element to use this functionality.

### GLTF Animations

User are now able to play animations from their 3D models in the Storefront.
Simply upload a model with one or multiple animations baked into the file, bind the file to a product, and display it in the Storefront.

### Show child line items if available

New block `component_line_item_type_product_children` added to template `storefront/component/line-item/type/product.html.twig` to display child line items if available

## App System

### App requirements validation

Apps can now declare requirements in their manifest via a new `<requirements>` element.
Requirements are validated during app installation and updates in production.
If a requirement is not met, the process fails with `FRAMEWORK__APP_REQUIREMENTS_NOT_MET` and an actionable message.

The first introduced requirement, `<public-access/>`, verifies that `APP_URL` uses HTTPS, does not point to an IP or reserved/local development host, and that `/api/_info/health-check` returns HTTP 200 when called from the Shopware server.
This helps catch misconfigurations before apps that rely on webhooks or other external communication fail silently.

```xml
<requirements>
    <public-access/>
</requirements>
```

Unknown requirements are ignored and logged as warnings.

## Hosting & Configuration

### Possibility to disable product search keyword indexing

The new configuration key `shopware.product.search_keyword.indexing` can be used to disable the product search keyword indexing.
This is helpful for stores that do not require search keywords and want to avoid the overhead of maintaining those indices while still having basic search functionality or using third-party search solutions.

### Configurable product search keyword relevance limit

The new configuration key `shopware.product.search_keyword.relevant_keyword_count` can be used to configure how many interpreted product search keywords are used for MySQL product search queries.
The default value remains `8` to preserve the current performance characteristics.
Increasing the value can improve result completeness for reordered search terms with AND logic, but can also increase query complexity.

# 6.7.9.0

## Features

### Product Open Graph fields for SEO and social sharing

Merchants can now set custom Open Graph title, description, and image per product in the product SEO tab in the administration.
These values are used for the storefront product detail page meta tags (`og:title`, `og:description`, `og:image`), improving how product links appear when shared on social media and in search results.
The fields are stored in the database, exposed via the Admin and Store API on the product entity, and default to the product meta title, meta description, and cover image when not set.

### Default CMS page ID now persisted for categories

Previously, when a category had no CMS page assigned, the default CMS page ID was only set at runtime during entity loading.
This caused missing `cmsPage` association data when loading categories with criteria that included the `cmsPage` association.

Now the default CMS page ID is automatically written to the database when a category is saved without a `cmsPageId`.
A migration also backfills all existing categories that have no CMS page assigned.

The `categoryLoaded` event listener has been removed from `CategorySubscriber` since the default CMS page ID is now always present in the database.
Sales channel-specific CMS page defaults continue to be applied at runtime during `salesChannelCategoryLoaded`.

The runtime-only field `cmsPageIdSwitched` on `CategoryDefinition` and `CategoryEntity` has been deprecated and will be removed in v6.8.0. It is no longer used internally.

### New internal comment for state machine state history entries

A new internal comment field was added to the state change modal which can be used to add additional information about a state change.
The internal comment is only visible in the administration and not shown to customers.
It can be found in the state machine state history modal (state change modal) on the detail page of an order.

### Use JSON-LD format for Structured Data

The Storefront now emits structured data as JSON-LD (`<script type="application/ld+json">` in the `<head>`) instead of scattered inline microdata attributes (`itemscope`, `itemtype`, `itemprop`).
JSON-LD is the preferred format and keeps structured data cleanly separated from the HTML markup.

In addition to replacing the existing microdata, several schema types that were missing entirely are now included:
a `WebSite` schema with `SearchAction` (enabling the Google Sitelinks Searchbox), a top-level `Organization` schema with the shop logo, an `ItemList` schema on category and search result pages, and `VideoObject` entries for product video media.

The migration is controlled by the new `JSON_LD_DATA` feature flag. When the flag is **off** (default), the existing microdata is rendered as before.
When the flag is **on**, JSON-LD is injected and all microdata is removed.
The old microdata is deprecated and will be removed with the next major release (v6.8.0).

The following schema types are now emitted as JSON-LD:

| Schema                                                             | Pages                                          |
|--------------------------------------------------------------------|------------------------------------------------|
| `WebSite` + `SearchAction`                                         | All pages (enables Google Sitelinks Searchbox) |
| `Organization` with logo                                           | All pages                                      |
| `WebPage` / `ProductPage` / `CollectionPage` / `SearchResultsPage` | All pages (type narrows per context)           |
| `BreadcrumbList`                                                   | All pages with a navigation breadcrumb         |
| `Product`                                                          | Product detail page                            |
| `ItemList`                                                         | Category pages, search results                 |

The `Product` schema on the product detail page is significantly more complete compared to the previous microdata:

- All product images are listed (previously only cover image via `itemprop`)
- `VideoObject` entries are emitted for any video media in the product's media collection
- `AggregateRating` now includes the required `ratingCount` (total number of approved reviews), sourced via an efficient aggregation query in `ProductPageLoader`
- Individual `Review` items (up to 10 most recent) are included alongside `AggregateRating`
- `OfferShippingDetails` with `ShippingDeliveryTime` is included for single-price products
- Dimensions (`weight`, `height`, `width`, `depth`) are typed as `QuantitativeValue` nodes
- `itemCondition` and a typed `seller` (`Organization`) are set on every `Offer`
- `gtin13` (EAN) and `mpn` (manufacturer number) are included when present

#### Extending the schema templates

Each schema lives in its own Twig template under `storefront/layout/structured-data/`. Every template exposes two blocks: an outer block that contains the full data-building logic, and an inner `_script` block that wraps just the `<script>` output. Plugins and themes can override either level using Shopware's standard template extension mechanism.

To add or change properties, override the `_script` block, merge your changes into the data variable (`productData`, `orgData`, `webPageData`, etc.), and call `{{ parent() }}`.

```twig
{# MyPlugin/Resources/views/storefront/layout/structured-data/json-ld-organization.html.twig #}
{% sw_extends '@Storefront/storefront/layout/structured-data/json-ld-organization.html.twig' %}

{% block layout_structured_data_organization_script %}
    {% set orgData = orgData|merge({
        'contactPoint': {
            '@type': 'ContactPoint',
            'contactType': 'customer service',
            'email': config('core.basicInformation.email')
        }
    }) %}
    {{ parent() }}
{% endblock %}
```

The available outer / script block pairs are:

| Template                         | Outer block                           | Script block                                 |
|----------------------------------|---------------------------------------|----------------------------------------------|
| `json-ld-webpage.html.twig`      | `layout_structured_data_webpage`      | `layout_structured_data_webpage_script`      |
| `json-ld-breadcrumb.html.twig`   | `layout_structured_data_breadcrumb`   | `layout_structured_data_breadcrumb_script`   |
| `json-ld-organization.html.twig` | `layout_structured_data_organization` | `layout_structured_data_organization_script` |
| `json-ld-website.html.twig`      | `layout_structured_data_website`      | `layout_structured_data_website_script`      |
| `json-ld-item-list.html.twig`    | `layout_structured_data_item_list`    | `layout_structured_data_item_list_script`    |
| `json-ld-product.html.twig`      | `page_product_detail_json_ld`         | `page_product_detail_json_ld_script`         |

### [Experimental] Use OpenSearch for Admin API searches

When the data in your store grows larger the administration might become slower, especially when searching for entities in lists.
This is because the administration relies only on the DB fulltext search. For larger stores, this can lead to performance issues and even timeouts.
Now it is possible to use OpenSearch for the administration and Admin API searches, which can significantly improve the performance of searches in the administration, especially for larger stores.
To enable this feature, you can set the `ENABLE_OPENSEARCH_FOR_ADMIN_API` feature flag to `true`. For more technical guidelines refer to the section in the [Hosting & Configuration updates](#feature-flag-for-enabling-opensearch-globally-in-the-admin-api).

### Online revocation request form

Customers can now conveniently submit revocation requests through an online form.
Similar to the existing Contact Form, the revocation form can be integrated and used via Shopping Experiences, allowing flexible placement within the storefront.

### External media thumbnail support

External media entities can now have external thumbnail URLs attached to them, which is useful for CDNs that provide pre-generated thumbnails alongside the main media file.

Two new API endpoints have been added:
- `POST /api/_action/media/{id}/external-thumbnails` - Add thumbnails to existing external media
- `DELETE /api/_action/media/{id}/external-thumbnails` - Remove all external thumbnails from media

Both endpoints require the target media entity to be external (i.e. its path must be an HTTP/HTTPS URL). Attempting to call them on regular file-based media returns an error.

When creating external media via `POST /api/_action/media/external-link`, you can now provide an optional `thumbnails` array directly in the request body:

```json
{
  "url": "https://cdn.example.com/image.jpg",
  "thumbnails": [
    { "url": "https://cdn.example.com/image-200x200.jpg", "width": 200, "height": 200 },
    { "url": "https://cdn.example.com/image-400x400.jpg", "width": 400, "height": 400 }
  ]
}
```

The same `thumbnails` payload shape is accepted by `POST /api/_action/media/{id}/external-thumbnails`.

### Support of long-running MySQL connections

It is now possible to use libraries like [`doctrine-mysql-come-back`](https://github.com/facile-it/doctrine-mysql-come-back), which wrap the default DBAL connection.
More information on how to set up, can be found here: https://developer.shopware.com/docs/guides/hosting/infrastructure/database.html#setup-for-long-running-environments

### System config overrides in staging mode

The `system:setup:staging` command now supports pre-configuring system config keys during staging setup. Both global and sales channel-specific values can be set, following the same YAML structure used for [static system configuration](https://developer.shopware.com/docs/guides/hosting/configurations/shopware/static-system-config.md).

Use `default` for global config values and sales channel IDs for channel-specific overrides:

```yaml
shopware:
  staging:
    system_config:
      default:
        core.mailerSettings.smtpHost: "smtp.staging.local"
        core.listing.allowBuyInListing: false
      0188da12724970b9b4a708298259b171:
        core.mailerSettings.smtpHost: "smtp.other.staging.local"
```

When `bin/console system:setup:staging` is executed, the configured keys are written to the database via `SystemConfigService`.

### Deprecation of newsletter route methods

The following methods are deprecated and will be removed with the next major version:

- `AbstractNewsletterSubscribeRoute::subscribe()` → use `subscribeWithResponse()` instead
- `AbstractNewsletterConfirmRoute::confirm()` → use `confirmWithResponse()` instead
- `AbstractNewsletterUnsubscribeRoute::unsubscribe()` → use `unsubscribeWithResponse()` instead

The new methods currently return `StoreApiResponse` in the abstract classes. In the next major version, the return types will change to their explicit types:

- `subscribeWithResponse()` → `NewsletterSubscribeRouteResponse`
- `confirmWithResponse()` → `SuccessResponse`
- `unsubscribeWithResponse()` → `SuccessResponse`

The Store API newsletter routes now return `200 OK` with a response body instead of `204 No Content`:

| Route                               | Response                                                       |
|-------------------------------------|----------------------------------------------------------------|
| `/store-api/newsletter/subscribe`   | `{"success": true, "status": "notSet\|optIn\|optOut\|direct"}` |
| `/store-api/newsletter/confirm`     | `{"success": true}`                                            |
| `/store-api/newsletter/unsubscribe` | `{"success": true}`                                            |

### OpenAPI enums via DAL `Choice` flag

DAL fields can now declare a finite set of allowed values using the `Choice` flag.
This information is used to enrich the generated OpenAPI schema with `enum` values for better API documentation and client generation.

By default, `Choice` is non-strict and does not affect write validation.
If you want to enforce values on write, set `strict: true` when creating the flag; the write layer will then validate the input for supported field types (string, int, float).

### Deprecated `/api/_action/mail-template/validate` route

The `/api/_action/mail-template/validate` route is deprecated and will be removed without replacement in v6.8.0.0, as it was not used and did not provide any significant value.

## Core

### Changed behaviour of default fields in EntityDefinition

Currently, it is not possible to overwrite the default fields `createdAt` and `updatedAt` of an entity in the definition.
This is because the default fields are applied on top of the fields defined in the `defineFields` method.
From the next major version on, the logic is turned around and the defined fields will be applied after the default fields.
This makes it possible to overwrite the current default fields `createdAt` and `updatedAt`.
Check your EntityDefinitions if this change will have an effect on your entities' behaviour. (Only applicable if you manually add `CreatedAtField` and/or `UpdatedAtField`)

### Product stream deletion is blocked while product exports exist

Deleting a product stream that's been used in a product export raises a dedicated delete restriction.
This rule is additionally enforced on database level by changing the foreign key delete action from `CASCADE` to `RESTRICT`.

### Reduced HTTP cache invalidation on system config changes

`SystemConfigService::set()`, `setMultiple()`, and `delete()` now accept an optional `$silent` parameter. When `silent=true`, the internal config cache is still cleared immediately, but the broad HTTP cache tag `system.config-{salesChannelId}` is not invalidated.

This prevents "invalidation storms" where writing internal config values (e.g. timestamps, license keys, store secrets) would wipe big amount of HTTP-cached pages.

Internal Shopware call sites that write non-storefront config values now pass `silent=true`. The `ConfigSet` CLI command accepts `--silent`, and the Admin API `POST /_action/system-config` and `POST /_action/system-config/batch` accept a `?silent` query parameter.

In v6.8.0.0, `silent` parameter in SystemConfigService methods will default to `true`. Clients should pass value explicitly to prepare for changes.

### Scheduled cleanup of expired customer recovery records

A new scheduled task `customer.cleanup_customer_recovery` has been added that automatically removes expired customer recovery records from the database on a daily basis.

Customer recovery records (password reset tokens) expire after 2 hours. Previously these records were never removed, causing the `customer_recovery` table to grow indefinitely. The new task deletes all records older than 48 hours.

### New attribute field types for entity definitions

The attribute-based entity definition system now supports additional field types:

- `FieldType::EMAIL` maps to `EmailField` for email validation
- `#[Password]` attribute for password fields with configurable hashing algorithm, hash options, and scope
- `#[ListField]` attribute for storing lists with optional typed field specification
- `FieldType::PRICE` maps to `PriceField` for price storage

### Inheritance added to product main categories

Product main categories are now inherited from parent product if not explicitly defined on the variant itself.

### CategoryIndexer doesn't dispatch IndexingMetaEvent when only index irrelevant data changes

The CategoryIndexer did already check for changed payload and only triggered the tree/child-count updaters when the `parentId` changed and the breadcrumb updater when the `name` changed.
But it still dispatched the `CategoryIndexingMessage`, even though all relevant Updaters would be skipped. For performance and efficiency reasons that event is not thrown anymore in the case of an update when only irrelevant data has changed.
This saves resources, as we don't need to fetch any child categories, dispatch unneeded messages and create DB transactions when it's not needed, especially as this whole handling was also triggered when you only assign products to a category, which is a quite common action.
Note that this only affects the update case, in the case of newly inserted or deleted categories the event is still dispatched, as all updaters are relevant in that case.

### Existing cart recalculations no longer recreate deleted carts

When an existing cart is recalculated, Shopware now uses the cart's persisted state to avoid recreating carts that were already deleted.
This prevents race conditions where a concurrent request, such as placing an order, deletes the cart and a stale recalculation writes it back afterwards.

### Deprecation of unused `TemplateGroup` class

The class `\Shopware\Core\Content\Seo\SeoUrlTemplate\TemplateGroup` has been deprecated as it is unused and will be removed in the next major version v6.8.0.

### New criteria events for product slider CMS element

Two new events are dispatched when the product slider CMS element resolves its product criteria, allowing subscribers to modify the criteria:

- `Shopware\Core\Content\Product\Events\ProductSliderStaticCriteriaEvent` is fired by the `StaticProductProcessor` when resolving a static product list.
- `Shopware\Core\Content\Product\Events\ProductSliderStreamCriteriaEvent` is fired by the `ProductStreamProcessor` when resolving a product stream.

### Allow custom HTTP client injection for S3 client creation

The S3 client creation flow (`S3ClientFactory`, `AwsS3v3Factory`, `PresignedUploadUrlGenerator`)
now accepts an optional `HttpClientInterface` parameter. When provided, this HTTP client is
forwarded to the underlying AsyncAws `S3Client` instead of letting it create its own default.

Both `AwsS3v3Factory` and `PresignedUploadUrlGenerator` are wired via DI to the new
`shopware.filesystem.s3.client` service ID. This service is not registered by default, so
`null` is injected and AsyncAws uses its own internal HTTP client. Integrators can register
the `shopware.filesystem.s3.client` service to provide a custom Symfony HTTP client with
custom timeouts, retry strategies, or HTTP protocol version for S3 operations.

### Events gain `Context` and implement `ShopwareEvent`

The events below now accept an optional `Context` as the last constructor argument and implement `ShopwareEvent`. Shopware's own dispatch sites already pass the context. Third-party code that instantiates these events without `$context` will see a deprecation notice; in 6.8, the parameter becomes required. A temporary `getNullableContext()` method is available for consumers who cannot guarantee the dispatcher passed context.

- `Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent`
- `Shopware\Core\Content\ImportExport\Event\ImportExportBeforeExportRecordEvent`
- `Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportExportHandlerEvent`
- `Shopware\Core\Content\Seo\Event\SeoUrlUpdateEvent`
- `Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent`
- `Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent`
- `Shopware\Storefront\Theme\Event\ThemeAssignedEvent`
- `Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent`
- `Shopware\Storefront\Theme\Event\ThemeConfigResetEvent`

### `#[Field]` attribute supports custom `maxLength` for string fields

The `maxLength` parameter is now available on `#[Field]` for `FieldType::STRING` and `FieldType::EMAIL` fields. Previously the max length was always 255, matching `StringField`'s default, with no way to override it. Setting `maxLength` passes the value through to the underlying `StringField` constructor and the `StringFieldSerializer` validation.

```php
#[Field(type: FieldType::STRING, maxLength: 4096)]
public ?string $url = null;
```

A value of `0` disables length validation entirely. This is pre-existing `StringFieldSerializer` behavior where any value below `1` is treated as unconstrained.

## Administration

### CMS data mapping source for media custom fields

Fixed media custom fields not being available as data mapping source for image elements in category and product CMS layouts. Shop Administrators can now reliably bind media custom fields to images in CMS pages without workarounds.

## Storefront

### Block renaming

* Deprecated block `page_product_detail_product_buy_button_label` in `Resources/views/storefront/component/product/card/action.html.twig` which will be removed in v6.8.0. Use block `component_product_box_action_buy_button_label` instead.

### Disabled runtime error overlay in webpack dev server

The webpack dev server overlay for runtime errors has been disabled in hot-reload mode. The overlay frequently interrupted the development workflow by covering the entire viewport for non-critical runtime errors, making it difficult to interact with the storefront during development. Error details remain available in the browser console.

### `HEAD`-requests do not trigger the registration double-opt-in

As some mail clients send `HEAD` requests to links which are contained in emails, the registration double-opt-in was sometimes already confirmed, as Symfony treats `HEAD`-requests the same as `GET`-request. Now `HEAD`-requests do not trigger the registration double-opt-in anymore, only "real" `GET`-requests.


### Avoid excessive use of @extend in Storefront SCSS

We have refactored the SCSS of the checkout related routes (cart, confirm, finish, register) to use `@include` mixins instead of `@extend` to apply the Bootstrap grid layout.
Using `@extend` on generic tooling classes was causing very large combined selectors. We are now using the grid mixins that are documented by Bootstrap.

#### Before
```scss
.checkout-main {
    @extend .col-lg-8;
}
```

#### After
```scss
.checkout-main {
    @include make-col-ready();
    @include media-breakpoint-up(lg) {
        @include make-col(8);
    }
}
```

In addition, we have refactored several places to use direct CSS or SCSS variables instead of `@extend`.

#### Deprecate stylings that have no usage in the DOM or no visual effect

* Deprecated `@extend .btn-lg` on `.btn-buy`. The current `.btn-buy` in combination with `.btn-lg` has the same dimensions as a normal button.
    * If you need a larger buy button, you can use the Bootstrap CSS variables or the `button-size` mixin to increase the size.
* Deprecated custom styling on class `.offcanvas-footer`. Class is not used in the DOM.

#### New stylelint rule to avoid `@extend`

Because of the side-effects with large combined selectors, we have added a new stylelint rule `scss/at-extend-no-missing-placeholder` that does not allow the use of `@extend` on generic selectors.
The use of `@extend` is still allowed on SCSS placeholder selectors (`%my-selector`) that are not included in the compiled CSS.
If you have good reasons to use `@extend` and can ensure that the combined selectors do not grow too large, the rule can still be ignored via inline comment.

## App System

## Hosting & Configuration

### Add custom HTML element configuration for HTML Sanitizer

A new config option `custom_tags` was added, to allow the usage of custom HTML elements using the Shopware CMS Pages and other text fields.

```yaml
shopware:
    html_sanitizer:
        sets:
            - name: basic
              custom_tags:
                  - tag: "your-custom-element"
                    type: "Block"
                    contents: "Flow"
                    attr_collections: ["Common"]
                    attributes:
                        - custom-attribute
```

# 6.7.8.2

## Critical Fixes

### Webhook for order state change

Fixed an undefined array key warning within the webhook handling, which could lead to a server error, if strict error displaying is set up.

### Digital product legacy states repair after update

We fixed a bug in the indexer for the `product.states` field, which lead to issues where rules (and flows depending on those rules) with the `line item with product state` condition did not work as expected. This especially affected the flows to deliver digital download products after purchase.

This release repairs digital products with missing legacy `states` via a one-time `UpdatePostFinishEvent` subscriber.

The repair runs automatically once per installation and is marked as completed in `app_config`.

# 6.7.8.1

## Critical Fixes

### Double signature verification in app-reregistration flow
Introduces a secure, asynchronous app secret rotation feature to the app system, including both API and CLI interfaces.
Added a new API endpoint and command for rotating app secrets, implemented the underlying rotation logic, and adjusted the app registration process to support secret updates and dual signature confirmation.
This increases security by enforcing a two-step verification process during app re-registration, ensuring that only authorized parties can update app secrets.

### LoginRoute and AccountService don't throw CustomerNotFoundException
The `LoginRoute` and `AccountService` have been updated to no longer throw a `CustomerNotFoundException` when a login attempt is made with an email address that does not exist in the system.
Instead, they will now throw a generic `BadCredentialsException` without revealing whether the email address is registered or not.
This change enhances security by preventing potential attackers from enumerating valid email addresses through error messages.

### Improve OrderRoute deepLinkCode filter type validation
Improve the logic in `\Shopware\Core\Checkout\Order\SalesChannel\OrderRoute::load` to ensure the `deepLinkCode` filter is an instance of `\Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter`.

# 6.7.8.0

## Features

### New internal comment for state machine state history entries
A new internal comment field was added to the state change modal which can be used to add additional information about a state change.
The internal comment is only visible in the administration and not shown to customers.
It can be found in the state machine state history modal (state change modal) on the detail page of an order.

## Core

### Indexing the product's custom fields

Custom fields used in product sorting and product streams, as well as those belonging to apps, are now included when indexing products with Elasticsearch.

### Deprecation of increment-based message queue statistics

The increment-based message queue statistics system is deprecated and will be removed in v6.8.0.0.

**What's changing:**
- The Administration notification center will no longer show indexing progress notifications (e.g., "X products will be indexed")
- API endpoint `GET /api/_info/queue.json` is deprecated - use `GET /api/_info/message-stats.json` instead

**Deprecated configuration options:**
- `shopware.admin_worker.enable_queue_stats_worker`
- `shopware.increment.message_queue`

**Deprecated code:**
- `IncrementGatewayRegistry::MESSAGE_QUEUE_POOL` constant
- Increment-based handling in `MessageQueueStatsSubscriber::onMessageHandled()`

**Why?**
The increment-based statistics were often inaccurate due to hardcoded multipliers and missing decrements in edge cases. The replacement functionality was introduced in https://github.com/shopware/shopware/pull/8698

**Immediate disable:**
To disable the deprecated functionality before v6.8.0.0:
```yaml
shopware:
    admin_worker:
        enable_queue_stats_worker: false
```

### Internal product streams

A new boolean field `internal` has been added to product streams with a default value of `false`.
This allows you to mark product streams as internal for system or plugin use, preventing them from appearing in merchant-facing selection lists throughout the Administration (e.g., in categories, cross-selling, CMS elements, or sales channels).

Use this feature when you need to create product streams programmatically that should not be modified or selected by shop administrators.

### Database table helper class

A new helper class `\Shopware\Core\Framework\Util\Database\TableHelper` was introduced,
which could be used to check the table for existence, columns, indexes, and foreign keys.

#### Deprecation of helper methods in `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper`

As consequence of the introduction of the new table helper class following methods are deprecated and will be removed with the next major version:
- \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper::columnExists
- \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper::columnIsNullable
- \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper::tableExists

### Migration generator improvements

The migration generator previously used a fixed format: `fk.<table-name>.<column>` for foreign key names.
Doctrine does not support this format and creates broken migrations; therefore, we changed to the format `fk__<table-name>__<column>` for foreign key names.

Also, the generator now sets `CASCADE DELETE` on foreign keys for the translation table references.

### CategoryIndexer selective indexing optimization

The `CategoryIndexer` now skips tree/child-count updaters when `parentId` hasn't changed, and breadcrumb updater when `name` hasn't changed. All updaters still run for `INSERT` and `DELETE` operations.

### Updated `doctrine/dbal` dependency

The `doctrine/dbal` dependency was updated to the new 4.4 minor version.
They introduced many deprecations, especially in the SchemaManager tool, which also might affect you.
Read more about it in their [upgrade guide](https://github.com/doctrine/dbal/blob/4.4.x/UPGRADE.md#upgrade-to-44).

### Primary key validation in `dal:validate` command

The `dal:validate` command now includes validation to detect mismatches between database PRIMARY KEY constraints and entity definition PrimaryKey flags.
This validation prevents silent failures where queries return correct `total` counts but empty `data` arrays due to entity hydration failures caused by inconsistent primary key definitions.
When a mismatch is detected, the command provides a clear error message indicating which fields differ between the database schema and the entity definition.

### Deprecation of default value for `serializer` in `#[Serialized]` field attribute

When you use `#[Serialized]` field in your attribute entity you should always pass the serializer explicitly, as the default serializer does not work as expected.
Additionally, the `SerializerField` will become internal in the next major release, as that field should be only used for attribute entities, but never directly in classic `EntityDefinitions`.

## Administration

### Options API backward-compatibility shim for Composition API components

When a Shopware core component is migrated from Options API to Composition API using `createExtendableSetup()`, existing plugin overrides written with `Shopware.Component.override()` now continue to work automatically via a compatibility shim — no immediate changes to your plugin are required.

The shim is activated transparently whenever an Options API override (containing `methods`, `computed`, `data`, `watch`, `mixins`, or lifecycle hooks) targets a component that has been converted to Composition API. It converts the override at runtime and logs a deprecation warning in the browser console directing developers to migrate to `Shopware.Component.overrideComponentSetup()`.

**What this means for you:**

- **No immediate action required.** Your existing `Shopware.Component.override()` calls continue to work, including `this.$super()` chaining, `data`, `computed`, `watch`, and `mixins`.
- **A deprecation warning will appear** in the browser console for each affected override. This is your signal to migrate.
- **Lifecycle hooks** (`mounted`, `created`, etc.) are supported by the shim and mapped to their Composition API equivalents.
- Dot-notation watch paths (e.g. `watch: { 'a.b.c': handler }`) are not supported and will be skipped with a console warning.

To migrate proactively or to silence the warnings, see the [Composition API Extension System migration guide](UPGRADE-6.8.md#migrating-options-api-overrides-to-the-composition-api-extension-system) in `UPGRADE-6.8.md`.

This feature is part of the experimental Composition API Extension System (`ADMIN_COMPOSITION_API_EXTENSION_SYSTEM` feature flag) and will become stable in v6.8.0.

### Product detail variants: `configSettingGroups` as computed and deprecations

In `sw-product-detail-variants`, the following changes were made:

* **`configSettingGroups`** (now computed): Previously a `data()` property set by `loadConfigSettingGroups()`. It is now a computed property derived from `productEntity.configuratorSettings` and `groups`.
* **`loadConfigSettingGroups()`** (deprecated): Marked as `@deprecated tag:v6.8.0`. It will be removed in 6.8.0 without replacement.

### Deprecation of `items` prop in `sw-entity-listing` component

The `items` prop in the `sw-entity-listing` component has been deprecated and will be removed in v6.8.0.
Please use the `dataSource` prop instead to align with the parent `sw-data-grid` component.

**Before (deprecated):**
```html
<sw-entity-listing
    :items="entityList"
    :repository="entityRepository"
    :columns="columns"
/>
```

**After (recommended):**
```html
<sw-entity-listing
    :data-source="entityList"
    :repository="entityRepository"
    :columns="columns"
/>
```

The component will continue to work with the `items` prop for backward compatibility, but you will see a deprecation warning in the browser console.

### Notification translations now update when language changes

Notifications now store translation keys directly in their title and message fields instead of translating them immediately.
The template checks if the text is a translation key and translates it reactively, allowing notifications to update automatically when the user changes the interface language.

### Help text support for color picker custom fields

The color picker type for custom fields now supports adding a help text. When creating or editing a custom field of type "Colorpicker" in Settings > Content > Custom fields, you can now specify a help text that will be displayed to users in the Administration.

### `sw-select-base` clearable button default behavior changed

The `showClearableButton` prop in `sw-select-base` now defaults based on the `required` attribute:
- When `required` is `false` or not set: clearable button is shown by default
- When `required` is `true`: clearable button is hidden by default

Previously, the clearable button was always hidden by default (`showClearableButton: false`).

**Migration:** If you relied on the previous behavior where the clearable button was hidden by default, explicitly set `:show-clearable-button="false"` on your select components.

## Storefront

### Form validation now supports the native HTML `pattern` attribute

The form validation helper (`FormValidation`) now automatically validates input fields with the native HTML `pattern` attribute. This allows you to specify regex patterns for input validation without additional JavaScript code.

**Example usage:**
```html
<input
    type="text"
    name="zipCode"
    pattern="[0-9]{5}"
    data-validation="required,pattern"
/>
```

The pattern validator will:
- Automatically activate when a `pattern` attribute is present on an input field
- Validate the input value against the specified regex pattern
- Show the appropriate error message if validation fails
- Skip validation for empty values (use the `required` validator to check for emptiness)

**Note:** The pattern attribute is now automatically included in the validation rules when present, similar to how the `required` attribute works. You can explicitly add it to `data-validation` for clarity, but it's not required.

### Selling and packaging information in the product detail page

* Display the selling and packaging information with the product that has advanced pricing.
* Deprecated block `buy_widget_price_unit` and it childrens in `Resources/views/storefront/component/buy-widget/buy-widget-price.html.twig`, will be moved into `Resources/views/storefront/component/buy-widget/buy-widget.html.twig`.

### Default theme breakpoints now available in theme config

The default layout breakpoints in the Storefront were hard-coded before and couldn't easily be overriden. Now you will find new theme config fields in the default config, which serve as the default values. The fields are hidden in the visual configuration, so they serve as a feature for theme developers for now. You can override the following config fields in your custom `theme.json` file to change the default breakpoints. The fields are mapped to the existing hard-coded configuration. The configuration is only passed in Twig and JS currently and there is no direct usage in SCSS, as they represent the Bootstrap default breakpoints. If you want to make a full override, you can simply configure the Bootstrap breakpoints in your custom SCSS and use the theme config values for that.

*  `sw-breakpoint-xs`
*  `sw-breakpoint-sm`
*  `sw-breakpoint-md`
*  `sw-breakpoint-lg`
*  `sw-breakpoint-xl`
*  `sw-breakpoint-xxl`

### Make static alerts announced in the screenreader

Static alert boxes that are rendered in the DOM on page load were previously not read out by screenreaders.
The `role="alert"` did not have an effect. Only `role="alerts"` added to the DOM by JavaScript were read out.

To solve the screenreader issue with static alerts, we introduced a new parameter `announceOnLoad`.
When `announceOnLoad` is set to true, the alert box content will be announced in the screenreader right after the page is loaded.
The alert box will apply an additional JavaScript plugin that attempts to trigger the screenreader.
This is done by changing the DOM within the `aria-live` region after a short delay, which tells the screenreader to read it.

```
{% sw_include '@Storefront/storefront/utilities/alert.html.twig' with {
    type: "primary",
    content: "An important message on initial page load",
    announceOnLoad: true
    ariaLive: 'assertive' {# Define the priority of the alert #}
} %}
```

## App System

### Fixed custom headers for app flow action webhooks in async mode

Custom headers defined in app flow action configurations are now correctly sent when webhooks are processed asynchronously via message queue (when `admin_worker` is disabled). Previously, these headers were only sent when `admin_worker` was enabled (synchronous processing).

### New webhook event: `app.system_heartbeat`

A new hookable event `app.system_heartbeat` was added to indicate that a Shopware instance is up and running.
This gives app developers a lightweight, platform-native heartbeat signal they can use for operational monitoring or connectivity checks without relying on custom polling.

The heartbeat is emitted by a recurrent scheduled task on a weekly basis, so apps should treat it as a periodic liveness signal, not as a strict scheduling mechanism or real-time telemetry signal.
No additional ACL privileges are required for this event.

## Hosting & Configuration

### OpenSearch PHP client updated to 2.6

Shopware now uses `opensearch-project/opensearch-php` `^2.6.0` and `shyim/opensearch-php-dsl` `^1.1.4` (PR #15832).
The Elasticsearch integration was migrated to the newer OpenSearch client transport for regular single-host configurations, and query generation now uses newer DSL APIs for tracked totals, result collapsing, and nested sorting.

Existing installations that configure a single OpenSearch endpoint via `OPENSEARCH_URL` or `ADMIN_OPENSEARCH_URL` do not need to change their configuration.
Comma-separated multiple-host values still work in 6.7 through the legacy OpenSearch client builder, but this fallback is deprecated and will be removed in 6.8.
If you currently configure multiple OpenSearch nodes directly, switch to a single load-balanced OpenSearch endpoint before upgrading to 6.8.

### Feature flag for enabling OpenSearch globally in the Admin API

The new feature flag `ENABLE_OPENSEARCH_FOR_ADMIN_API` (see `adr/2026-01-28-apply-opensearch-in-admin-api.md`) can be used to activate that now all supported searches and reads from the administration and Admin-API are handled by OpenSearch instead of the DB.
Especially when you have a large amount of data in your shop, this can lead to significant performance improvements in the administration.
The downside is that the indexing of the data into OpenSearch takes slightly longer, and the results you see might be slightly delayed as they are not read directly from the DB anymore, but need to be indexed to OpenSearch first.
When the flag is disabled (which is the default), Administration lists, filters, and DAL searches continue to use MySQL exactly as in previous releases.
Once enabled, supported Admin API entities reuse the Admin OpenSearch indices for criteria-based searches, which requires admin OpenSearch to be configured and re-indexed via `bin/console es:admin:index`.

### New config option to fine tune Admin OpenSearch indexing

There is a new config option `elasticsearch.admin.indexing_batch_size` that allows you to configure the batch size for Admin OpenSearch indexing.
The same config can be set via environment variable `SHOPWARE_ADMIN_ES_INDEXING_BATCH_SIZE`. The default value is `1000`, which means that entities will be indexed in batches of 1000.
This should reduce the overhead needed when running the admin index process.
Before the admin indexing process shared the same config `elasticsearch.indexing_batch_size` (default value: 100) with the Storefront/Store API indexing, which could lead to performance issues when you had a large amount of data in your shop, as the admin indexing process is usually way faster and therefore can benefit from higher batch sizes.

### Optional precision threshold for grouped OpenSearch product counts

There is a new optional config option `elasticsearch.search.precision_threshold` that allows you to configure the `precision_threshold` sent for grouped Storefront product count aggregations in OpenSearch.
When the config is not set, Shopware keeps the current OpenSearch behavior and does not send `precision_threshold`.
This can be useful for large catalogs that use grouped product listings and need to trade higher count accuracy against additional OpenSearch memory usage.

### Deprecated HTTP cache reverse proxy configuration

The following HTTP cache reverse proxy configuration options have been doing nothing since 6.7.0.0 and are therefore now deprecated. They will be removed in version 6.8.0.0:

- `shopware.http_cache.reverse_proxy.use_varnish_xkey`
- `shopware.http_cache.reverse_proxy.ban_method`
- `shopware.http_cache.reverse_proxy.ban_headers`
- `shopware.http_cache.reverse_proxy.purge_all`
  - `shopware.http_cache.reverse_proxy.purge_all.ban_method`
  - `shopware.http_cache.reverse_proxy.purge_all.ban_headers`
  - `shopware.http_cache.reverse_proxy.purge_all.urls`

If you are currently using any of these options, you can safely remove them from your configuration.

### Configurable Elasticsearch shard and replica counts

The `number_of_shards` and `number_of_replicas` settings for Elasticsearch indices are now configurable via environment variables instead of being hardcoded.

For the Storefront/Store API Elasticsearch:
- `SHOPWARE_ES_NUMBER_OF_SHARDS` (default: empty, meaning Elasticsearch default)
- `SHOPWARE_ES_NUMBER_OF_REPLICAS` (default: empty, meaning Elasticsearch default)

For the Admin Elasticsearch:
- `SHOPWARE_ADMIN_ES_NUMBER_OF_SHARDS` (default: `3`, will also be empty with next major)
- `SHOPWARE_ADMIN_ES_NUMBER_OF_REPLICAS` (default: `3`, will also be empty with next major)

## Critical Fixes

### Session deadlock fix for file-based sessions

A new configuration option `shopware.cache.disable_stampede_protection` has been added to prevent deadlocks when using file-based sessions with Symfony's cache stampede protection.

**Problem**: A deadlock (ABBA pattern) can occur when:
- Process 1: Acquires Session File Lock → Needs Cache → Tries to acquire Cache Lock
- Process 2: Acquires Cache Lock (stampede protection) → Needs Session → Tries to acquire Session File Lock

**Solution**: Set `shopware.cache.disable_stampede_protection: true` in your configuration to disable file-based cache locking when file-based sessions are in use.

```yaml
shopware:
    cache:
        disable_stampede_protection: true
```

**Note**: This is an opt-in fix for environments where Redis is not available. Using Redis for both sessions and cache is the recommended solution. Disabling stampede protection may increase database load under high concurrency when cache entries expire.

# 6.7.7.1

## Core

### Dependency on Elasticsearch Bundle

Removed dependency of the Core bundle to the Elasticsearch bundle, so that the Core bundle can be used without Elasticsearch again.

# 6.7.7.0

## Features

### Symfony 7.4 update

All Symfony packages have been updated to version 7.4.
Take a look at the [Symfony 7.4 release post](https://symfony.com/blog/symfony-7-4-0-released) for more information.
Especially note that Symfony now requires php-redis extension v6.1 or higher: https://github.com/symfony/symfony/blob/7.4/UPGRADE-7.4.md#cache.
If you note compatibility issues with the Redis extension please check the installed version php-redis.

### Changed maintenance mode redirect

After maintenance ends, users are now redirected back to the page they were on before maintenance.
Previously, users were always redirected to the shop homepage.

### Support of media paths with up to 2046 characters

Previously the maximum length for media paths was limited to 255 characters (due to default StringField limit) while the database field already supported up to 2046 characters.
This limitation has now been lifted, and media paths can be up to 2046 characters long.

### Configurable Custom Field Searchability

Custom fields are now **not searchable by default**.
To make a custom field searchable, you need to enable the "Include in search" option in the custom field detail modal when creating or updating a custom field in Settings > System > Custom fields.
This change helps optimize index storage size and improve search performance, especially for stores with many custom fields.

**Important:** When enabling searchability for an existing product custom field, you must rebuild the search index or update the products manually to include the custom field data in search results.

### Media Model Viewer

From now on you are able to inspect your 3D models directly in the Media module in the Administration.
Simply select a model file and you will find an interactive 3D viewer in the Preview collapsable in the item sidebar on the right.
This new component is called `sw-model-viewer`.

### Media Model Editor

The Model Editor lets you make quick adjustments to your 3D models directly in the Administration. No external software needed.
Simply select a 3D model in the sidebar and click the Expand button on the Model Viewer.
A modal will open where you can move, rotate, and scale the model.
You can also use the sidebar to type in specific values for position, rotation, and scale.
Click Save, and your changes are applied instantly.

## API

### Improved tagged-based cache invalidation

The following routes now support cache tagging, enabling automatic invalidation when relevant entities are written:
* `/store-api/breadcrumb/{id}`
* `/store-api/media`
* `/store-api/product/{productId}/find-variant`
* `/store-api/product/{productId}/cross-selling`

## Core

### Rework of DAL query generation for nested filters groups
The DAL criteria builder has been adjusted to generate `EXISTS` subqueries instead of `LEFT JOIN`s for nested filter groups.

Previously, each level of nested filters resulted in an additional `LEFT JOIN`, even when the join was only required to check for the existence of a related entity subject to some filter.
In complex criteria trees with multiple filters on the same entity, this led to an exponential explosion of joins and significant performance degradation (e.g., the same table being joined multiple times only to evaluate existence conditions).

An example of this is a query such as "find orders that have a line item of type A and one of type B and one of type C".
According to [aadr/2020-11-19-dal-join-filter.md](adr/2020-11-19-dal-join-filter.md), this would look like:
```php
$criteria->addFilter(
    new EqualsFilter('lineItems.type', 'product'),
    new EqualsFilter('lineItems.type', 'custom'),
    new EqualsFilter('lineItems.type', 'other'),
);
```
Previously, the generated query would `LEFT JOIN` `order_line_item` multiple times onto `order`, causing the query to be extremely slow. The new `EXISTS` checks prevent this, making the query much faster.

### Introduce Immutable DAL flag

A new `Immutable` flag is available for Data Abstraction Layer fields.
Fields marked as immutable can be set during entity creation but cannot be updated later.
This prevents accidental renames of technical identifiers that other subsystems rely on.
Core entities now using the flag include:

* `custom_field.name`
* `custom_field.type`
* `custom_field_set.name`

Trying to update these columns now results in a `WriteConstraintViolationException` with the message `The field foo is immutable and cannot be updated.`, giving developers clear feedback when attempting to change these values.
If the value is not set in the payload, or the value won't change, no exception is thrown.

### Performance Improvement for `ProductCategoryDenormalizer`

The SQL Query inside the `ProductCategoryDenormalizer` has been optimized to run faster, especially on large catalogues.
Previously MySql needed to perform a full table scan based on the where condition, now the result set is already limited by indexed columns.
This lead to performance improvements from up to 3s for the query down to less than 1ms on large catalogues (3000%).

### Deprecation of product states in favor of the new product type

The `product.states` field is deprecated and will be removed in the next major release.
A new field `product.type` was introduced to clearly indicate whether a product is `digital` or `physical`, or other types registered by third-party developers.

As part of this change, the following deprecations were made:
- The `order_line_item.states` field is deprecated in favor of `order_line_item.payload.product_type`.
- `\Shopware\Core\Checkout\Cart\LineItem\LineItem::$states` is deprecated in favor of `\Shopware\Core\Checkout\Cart\LineItem\LineItem::$payload['productType']`.
- The `LineItemProductStatesRule` is deprecated in favor of the new `LineItemProductTypeRule`.
- The `StatesUpdater` service and its related dispatched events (`ProductStatesBeforeChangeEvent`, `ProductStatesChangedEvent`) are deprecated.
- A new parameter `shopware.product.allowed_types` was introduced to allow third-party developers to register additional product types.
- For more details, please refer to the [2025-11-14-introduce-product-type-and-deprecate-states.md](adr%2F2025-11-14-introduce-product-type-and-deprecate-states.md)

If you are using the rule `LineItemProductStatesRule`, product stream filters, or product listing filters that rely on `product.states`, you should update them to use the new `product.type` field instead.
If you create digital products using admin api, you should explicitly set the `type` field to `digital` when creating new products instead of relying on backend handling.

### New `RequestParamHelper`

Symfony deprecated the "magic" `Request::get()` method, which was used to retrieve parameters from the request, by checking the `attribute`, `query` or `request` parameter bags.
For easier backward compatibilty we backported the old behaviour in the new `RequestParamHelper` class, however, it should only be used in explicit cases, where the parameter could be in any of those parameter bags.
The best practice is to check the explicit parameter bag, where you expect the parameter to be.
However, as we have a lot of API routes that support being called by `GET` and `POST` methods both, the helper is handy in such cases.

Before:
```php
$parameter = $request->get($parameterName, $default);
```
After:
```php
$parameter = RequestParameterHelper::get($request, $parameterName, $default);
```

To provide full backward compatibility, the helper currently also checks the `attribute` bag for the parameter first.
However, it should be possible to strictly differentiate between request attributes (which are generally controlled and set by the application itself) and input parameters (which are provided by the client, and based on how they are passed are either part of the query bag or the request bag) in the future.
Therefore the check of the `attribute` bag is deprecated and will be removed in the next major release.
When you need to get a value from the request attributes, you should use the `Request::attributes->get()` method directly.
In case you used to set request attributes to override specific parameters, you should instead overwrite the parametes in the `query` or `request` parameter bags directly.

### The `TranslationLoader` class is now decoratable

The `TranslationLoader` class extends from the new `AbstractTranslationLoader` class and implements the decoratable pattern. This allows third-party developers to decorate the loader to add custom logic when a translation is loaded.

### DomainExceptions don't create \RuntimeException anymore

All factory methods for domain exceptions now return specific exception classes instead of creating a generic `\RuntimeException`.
Changing the type of the thrown exception from `\RuntimeException` to a specific domain exception is not considered a breaking change, since all Domain Exceptions extend from `\RuntimeException`.

This means code like this will stay valid:
```php
try {
    $this->someService->willThrowDomainException();
} catch (\RuntimeException $e) {
    // handle exception
}
```

Additionally all changed factory methods were marked as deprecated, because the `\RuntimeException` return type will be removed in the next major release.
This affects the following exception factory methods:
* `DataAbstractionLayerException::cannotBuildAccessor(...)`
* `DataAbstractionLayerException::onlyStorageAwareFieldsAsTranslated(...)`
* `DataAbstractionLayerException::onlyStorageAwareFieldsInReadCondition(...)`
* `DataAbstractionLayerException::primaryKeyNotStorageAware(...)`
* `DataAbstractionLayerException::missingTranslatedStorageAwareProperty(...)`
* `DataAbstractionLayerException::noTranslationDefinition(...)`
* `DataAbstractionLayerException::missingVersionField(...)`
* `DataAbstractionLayerException::unexpectedFieldType(...)`
* `WebhookException::invalidDataMapping(...)`
* `WebhookException::unknownEventDataType(...)`

### More fine-grained caching control in `HttpCacheCookieEvent`

A new `doNotStore` property was added to the `HttpCacheCookieEvent` to allow fine-grained control over caching behavior.
This new property allows preventing the current response from being stored in the cache.
This behaviour differs from the existing ìsCacheable` property, which will also prevent the following requests from that session being cached.

### Logging for invalidated cache tags

Added logging for invalidated cache tags at the info level, with the ability to enable or disable the logging via configuration for debugging and transparency.

### Removed `CacheInvalidationSubscriber::getChangedPropertyFilterTags` due to performance issues

The `getChangedPropertyFilterTags` method has been removed from `CacheInvalidationSubscriber` due to performance issues where it could cause invalidation storms by selecting all product IDs for popular property options.

Changing a property group or option will no longer automatically invalidate product and product list caches. It's recommended to rely on TTLs for bigger shops. If you experience issues after changing a property group, a manual cache clear may be required.

## Administration

### Refactored media modal from `sw-modal` to `mt-modal`

The media modal in Shopping Experiences has been refactored from `sw-modal` to `mt-modal`. This fixes an issue where elements inside the "open media" modal could not be focused when the CMS extension was installed.

### Deprecations in mail template components

The mail template index will be split into separate tabs for templates and headers/footers in v6.8.0.0.

The following deprecations apply to `sw-mail-template-list` and `sw-mail-header-footer-list`:
* `searchTerm` prop and watcher will be removed in v6.8.0.0
* `getList()` method: `searchTerm` variable will be replaced with `this.term` in v6.8.0.0
* `@page-change` handler will change to `onPageChange` in v6.8.0.0

The following deprecations apply to `sw-mail-template-index`:
* The `listing` mixin will be removed in v6.8.0.0
* `term` data property will be removed in v6.8.0.0
* `onChangeLanguage` method: the if/else block will be replaced with just the if-branch logic in v6.8.0.0

### Fixed `sw-entity-multi-id-select` crash when used in plugin system config with sales channel inheritance

When `sw-entity-multi-id-select` was used via the `<component>` tag in a plugin's `config.xml`, switching to a non-default sales channel caused a TypeError because the inheritance system passed `null` as the value instead of an array. The component now handles `null` gracefully, aligning with the convention that components used via `<component>` in system config must accept `null` as their value.

### Admin boot loading spinner shows error instead of infinite loading

The loading spinner shown while the admin is booting up no longer spins indefinitely when an error occurs. The error is now displayed instead.

## Storefront

### Cookie consent now language-aware

The cookie consent banner now tracks cookie configuration per language. Previously, switching languages would cause the cookie banner to reappear because the configuration hash changed due to translated cookie descriptions. Now, switching back to a previously accepted language will not show the banner again.

The Store API endpoint `/store-api/cookie-groups` now includes a `languageId` field in the response.
### New `window.activeNavigationPathIdList` variable

A new global JavaScript variable `window.activeNavigationPathIdList` is now available, containing the IDs of parent categories for the current page. This can be used by plugins or themes to implement custom navigation highlighting.

### Improved cookie consent dialog UI and accessibility

The cookie consent dialog now uses toggle switches instead of checkboxes for a more modern look. Additionally, accessibility improvements were made by adding proper ARIA attributes (`role="switch"`, `aria-disabled`, `aria-labelledby`) and converting links to semantic buttons where appropriate.

### HTTP caching policies update

The following changes are relevant when HTTP caching policies feature is enabled (`CACHE_REWORK` or `v6.8.0.0` feature flag):

* HTTP caching policy system now takes into account `_noStore` route attribute to apply `no-store` directive in Cache-Control header.
* `Cache-Control` header set by policies is sent to the client for all responses, even when no reverse proxy is enabled. Previously, headers were replaced with `no-cache` when no reverse proxy was configured. **Important**: Verify your cache policy configuration is appropriate for client-side caching, as browser caches cannot be invalidated on-demand unlike reverse proxies that use tag-based invalidation.

### First tap on iOS Safari did not trigger call-to-action buttons on product detail page
Fixes an issue on iOS Safari where the first tap does not trigger the desired action on the product detail page after scrolling over the image gallery.
The `touchmove` event listener was removed from `zoom-modal.plugin.js` because it stopped the tap/click event.
A regular `click` event is used instead to open the Zoom-Modal. The browser itself can determine via the `click` event if the user is still scrolling or clicking/taping.

### Better handling of JS plugin initialization for async content
When content was loaded asyncronously within offcanvas elements or modals, all JS plugins of the page were initialized again, causing that update methods of all plugins to be called. We added a new method `initializePluginsInParentElement()` to the plugin manager to enable plugin initialization scoped to a parent element. This creates the possibility to initlize plugins only within newly added or async fetched content. The correposnding calls were updated in the following plugins:

*  `ajax-offcanvas.plugin.js`
*  `offcanvas-cart.plugin.js`
*  `offcanvas-menu.plugin.js`
*  `offcanvas-tabs.plugin.js`
*  `ajax-modal.plugin.js`

### Google Analytics 4 Integration Update

The Google Analytics integration has been updated to align with `GA4` standards, enhancing e-commerce tracking capabilities.

- The event parameters for `add_to_cart`, `begin_checkout`, `purchase`, `view_item`, and `remove_from_cart` have been enriched with additional data such as `currency`, `value`, `item_brand`, and a hierarchical `item_category` structure.
- Furthermore, new events for `add_to_wishlist`, `remove_from_wishlist`, `view_cart`, `add_shipping_info`, and `add_payment_info` have been implemented to provide a more comprehensive view of user interactions.
- The checkout funnel now tracks shipping and payment method selections, including when users change their selections.
- The `view_item_list` and `add_to_cart` events now fire when users navigate through product listings via pagination or apply filters, not just on initial page load.

#### New Configuration: Track Offcanvas Cart

A new configuration option `Track offcanvas cart` has been added to the Sales Channel Analytics settings. When enabled, the `view_cart` GA4 event will fire whenever the offcanvas cart is opened or its content is updated (e.g., quantity changes, product removals, promotions).

#### New Configuration: Open Offcanvas Cart After Add to Cart

A new configuration option `Open offcanvas cart after adding a product` has been added to the Cart settings (Settings → Shop → Cart). This setting is enabled by default. When disabled:

1. The offcanvas cart will **not open automatically** after adding items to the cart
2. A success message will be shown instead
3. The cart widget in the header will still update to show the new item count

**Recommended for accurate funnel tracking:** Disable "Open offcanvas cart after adding a product" and enable "Track offcanvas cart". This ensures `view_cart` events only fire when users intentionally click the cart button, providing accurate funnel metrics.

## App System

## Hosting & Configuration

## Critical Fixes

### Flash messages are not cached anymore

As soon as a flash message is displayed, the response won't be stored in the HTTP cache anymore, thus preventing the message from being displayed to other users.
Additionally, the cache will be passed as soon as there is a flash message that still needs to be displayed. This ensures that flash messages are always displayed on the next request, and not only on the next request to an uncached page.

# 6.7.6.0

## Features

### HTTP caching rework

- Support for HTTP caching policies was added. It allows defining HTTP cache behavior per area (storefront, store_api)
  and per route using configuration. The feature is experimental and can be enabled with the `CACHE_REWORK` feature flag
  together with other HTTP caching improvements.
- Selected Store API routes were marked as cacheable and now support HTTP caching with Cache-Control headers.

### Send email on customer password change
A new flow has been introduced which sends a confirmation email whenever a customer changes their password. This helps to identify any suspicious account activity more quickly.

## API

### Video cover management `/api/_action/media/{mediaId}/video-cover`
Added endpoint to assign or remove cover images for video media files. Requires `media.editor` ACL permission.
Accepts `coverMediaId` (string or null) in request body.
Cover image reference is stored in `metaData.video.coverMediaId`.
When a cover image is deleted, all video references are automatically cleaned up via `VideoCoverCleanupSubscriber`.

### StoreAPI HTTP caching support
HTTP caching support was added for the following Store API endpoints:
- `/store-api/breadcrumb/{id}`
- `/store-api/category`
- `/store-api/category/{navigationId}`
- `/store-api/navigation/{activeId}/{rootId}`
- `/store-api/cms/{id}`
- `/store-api/product`
- `/store-api/seo-url`
- `/store-api/country`
- `/store-api/country-state/{countryId}`
- `/store-api/currency`
- `/store-api/language`
- `/store-api/salutation`

`GET` methods and HTTP caching support were added for the following Store API endpoints:
- `/store-api/media`
- `/store-api/product/{productId}/cross-selling`
- `/store-api/product/{productId}`
- `/store-api/product/{productId}/find-variant`
- `/store-api/product-listing/{categoryId}`
- `/store-api/product/{productId}/reviews`
- `/store-api/search`
- `/store-api/search-suggest`
- `/store-api/landing-page/{landingPageId}`

It's intended to work with the new HTTP caching policy system, and should increase performance for cacheable Store API requests.

### Store API: compressed criteria parameter support
Criteria can be passed in the GET requests as single query parameter, encoded as JSON -> gzip -> base64url. This allows
sending complex criteria without hitting URL length limits. Also, ProductListingCriteria fields are supported.
Please note that this is a temporary workaround intended to be used until `QUERY` request method is standardized and supported.
Check the [ADR](adr/2025-09-15-store-api-cache-strategy.md) for more details.

### Document download `/store-api/document/download/`
The endpoint now selects the document file type based on the `Accept` header.
When no `Accept` header is set or with `*/*`, `PDF` will be returned. (PR #12944)

## Core

### PHP 8.5 support

Shopware is now fully compatible with PHP 8.5.

### Deprecation of `sw-states` and `sw-currency` handling and new way to disable caching

The `sw-states` and `sw-currency` handling is deprecated, which means by default the HTTP-Cache will also be active for logged in customers or when the cart is filled in the next major version.
You can opt in to the new behaviour by activating either the `v6.8.0.0` (all upcoming breaking changes),  `PERFORMANCE_TWEAKS` (all performance related breaks) or `CACHE_REWORK` (only the HTTP-Cache related breaks) feature flag.

Due to the rework of the contained rules in the cache hash, this becomes efficiently possible. The complete caching behaviour is now controlled by the `sw-cache-hash` cookie.

You should rework you extensions to also work with enabled cache for logged in customers and when the cart is filled.
To modify the default behaviour there are several extension points you can hook into, for a detailed explanation please take a look at the [caching docs](https://developer.shopware.com/docs/guides/plugins/plugins/framework/caching/#manipulating-the-cache-key).

The following classes and constants were deprecated as they will not be used anymore:
* `\Shopware\Core\Framework\Adapter\Cache\Http\CacheStateValidator`
* `\Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber`
* `\Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE`
* `\Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER`
* `\Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::CURRENCY_COOKIE`
* `\Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber::STATE_LOGGED_IN`
* `\Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber::STATE_CART_FILLED`

Additionally, the following configuration was deprecated:
* `shopware.cache.invalidation.http_cache`

### HTTP Caching Policies

Added support for caching policies to define HTTP cache behavior via configuration.

You can now configure named caching policies that define how the Cache-Control header is formed. These policies can be assigned per area (`storefront`, `store_api`) and per route. The header controls how caches (browser, reverse proxy, CDN, Symfony cache layer) should cache the response.

The feature is enabled using the `CACHE_REWORK` feature flag. For more details see the [caching policies documentation](https://developer.shopware.com/docs/guides/hosting/performance/caches.html#http-caching-policies).

### Add recursive assign method to AssignArrayTrait

A new method `assignRecursive` has been added to `Shopware\Core\Framework\Struct\AssignArrayTrait`. Along with it, the new `Shopware\Core\Framework\Struct\AssignArrayInterface` has been introduced.
To make full use of `assignRecursive`, every class using `AssignArrayTrait` must also implement the new `AssignArrayInterface`.
The `assignRecursive` method enables deeply nested, JSON-serialized data structures - for example, a fully serialized `ProductEntity` including associations such as `properties` - to be converted back into a fully populated `ProductEntity` instance, including all nested `Struct` and `Collection` objects.

Note: `assignRecursive` uses reflection and creates nested struct instances, so it is noticeably slower than the classic shallow `assign` and is intended for import/export and (re-)hydration scenarios rather than tight, performance-critical loops.

### Improved translation installation

Installing a translation now will always create a corresponding snippet set. This fixes issues with shop instances that are migrating from translations provided by a plugin to the core, where uninstalling the plugin could lead to a missing snippet set.

### Performance improvements for generating category SEO-Urls

We don't synchronously fetch and generate the SEO-Urls for all child categories anymore.
Instead, we rely on the CategoryIndexer to trigger the re-index of children asynchronously.
This prevents cases where SEO-Urls were generated multiple times for the same category, and thus it considerably improves the performance of category indexing.

### Make the find best variant on searching as non default behaviour

Since [6.7.2.0](https://github.com/shopware/shopware/pull/11107), the "find best variant" feature was always the default behaviour on the search. It means that if a product has variants, the best matching variant is returned instead of what merchant has configured in the product's Storefront presentation > Product listings > "Show main product or variant" setting.
This behaviour is now optional and can be enabled by setting the `core.listing.findBestVariant` config to `true` or setting it via the admin interface under Settings > Products > "Preview best matching variant for search results"

## Administration

As part of this change, the following deprecations were made:
- The `order_line_item.states` field is deprecated in favor of `order_line_item.payload.product_type`.
- `\Shopware\Core\Checkout\Cart\LineItem\LineItem::$states` is deprecated in favor of `\Shopware\Core\Checkout\Cart\LineItem\LineItem::$payload['productType']`.
- The `LineItemProductStatesRule` is deprecated in favor of the new `LineItemProductTypeRule`.
- The `StatesUpdater` service and its related dispatched events (`ProductStatesBeforeChangeEvent`, `ProductStatesChangedEvent`) are deprecated.
- A new parameter `shopware.product.allowed_types` was introduced to allow third-party developers to register additional product types.
- For more details, please refer to the [2025-11-14-introduce-product-type-and-deprecate-states.md](adr%2F2025-11-14-introduce-product-type-and-deprecate-states.md)

If you have using the rule `LineItemProductStatesRule`, product stream filters, or product listing filters that rely on `product.states`, you should update them to use the new `product.type` field instead.
If you create digital products using admin api, you should explicitly set the `type` field to `digital` when creating new products instead of relying on backend handling.

## Administration
When the initial page takes more than two seconds to load, a loading indicator appears instead of a blank page.
### Axios upgrade with dual-client dispatcher

The Administration now supports axios 1.x alongside the existing axios 0.30.2 to address security vulnerability CVE-2023-45857. A dual-client dispatcher pattern has been implemented that allows both versions to coexist, enabling a gradual migration path for plugins and custom code.

**Current behavior (6.7.x):**
- Default: axios 0.30.2 (backward compatible)
- Opt-in: Add `useAxiosV1: true` to request configuration to use axios 1.x

**Future behavior (6.8.0+):**
- Default: axios 1.x (when `V6_8_0_0` feature flag is active)
- Opt-out: Add `useAxiosV1: false` if axios 0.30.2 is still needed

**Key differences between versions:**
- **Cancellation**: axios 0.x uses `CancelToken`, axios 1.x uses `AbortController` (modern standard)
- **Error codes**: axios 1.x provides more standardized error codes like `ERR_CANCELED`

Plugin developers should test their code with `useAxiosV1: true` to ensure compatibility before the 6.8 release. The migration guide is available at `technical-docs/09-security/axios-migration-guide.md`.

### Loading indicator for whole page

When the initial page takes more than two seconds to load, a loading indicator appears instead of a blank page.

### Search filter for settings module

In the settings module, there is now a search bar in the top right. It can be used to filter settings based on a search term to quickly find what you need.

## Storefront

### The email validation supports IDN email addresses

The domain part of email addresses may now contain internationalized domain names (IDN). The Storefront validation will properly check these domains. The form validation in PHP may still deny IDN emails addresses, but the default Shopware forms already allow them.

### BuyBox JavaScript Plugin

The options `modalTriggerSelector` and `urlAttribute` as well as the former private methods `_initModalTriggerEvent()` and `_openTaxInfoModal()` have been removed from `buy-box.plugin.js` and have no effect anymore. The Ajax modal now reinitializes event handlers via `initializePlugins()` after the request, which also resolves an issue where changing a product variant in the buy box was not possible when the cms-element was used in a shopping experience.

## App System

### App Script caching control

As before, app developers can control caching via in app scripts using syntax `{% do response.cache.<directive> %}`, which map to `ResponseCacheConfiguration` methods.
Next changes were made to `ResponseCacheConfiguration` methods:
- added `sharedMaxAge(seconds)` - set shared (reverse proxy/CDN) cache TTL, equivalent to `s-maxage` cache control directive.
- added `clientMaxAge(seconds)` - set client-side (browser) cache TTL, equivalent to `max-age` cache control directive. Has effect only if `CACHE_REWORK` feature flag is enabled.
- deprecated `maxAge(seconds)` - use sharedMaxAge() instead.

Admins can override policies per script using `route_policies` with `route#hook` pattern in configuration (see HTTP caching policies description in the Core section).

## Hosting & Configuration

### Control language analyzer usage in Elasticsearch search queries

A new environment variable `SHOPWARE_ES_USE_LANGUAGE_ANALYZER` has been added to control whether language-specific analyzers (like `sw_english_analyzer`, `sw_german_analyzer`) are used for search queries.

By default (`SHOPWARE_ES_USE_LANGUAGE_ANALYZER=1`), search queries use the same analyzer as the indexed field, which includes language-specific features like stopword filtering and stemming. This provides broader, more fuzzy search results.

When set to `0` (`SHOPWARE_ES_USE_LANGUAGE_ANALYZER=0`), search queries use `sw_whitespace_analyzer` instead, providing less fuzzy search results with fewer matches.

**Note:** This setting only affects search queries, not indexing. Indexed data continues to use language analyzers for proper tokenization.

### Possibility to disable extensions when setting up staging mode

A new config option `shopware.staging.extensions.disable` was added to allow configuring extensions that should be automatically disabled when the staging mode gets activated via `system:setup:staging` command.

```yaml
shopware:
    staging:
        extensions:
            disable: ["TheExtensionName", "AnotherExtensionName"]
```

### Deprecated HTTP cache configuration

- `SHOPWARE_HTTP_DEFAULT_TTL` environment variable.
- `shopware.http.cache.default_ttl` parameter.
- `shopware.http_cache.stale_while_revalidate` parameter.
- `shopware.http_cache.stale_if_error` parameter.

Deprecated parameters will have no effect when `CACHE_REWORK` feature flag is enabled, and will be removed in 6.8.0.0.

# 6.7.5.0

## Features

### Tax Calculation Logic

The tax-free detection logic if the cart changed to handle B2B and B2C customers separately.
Previously, enabling "Tax-free for B2C" in the country settings also affected B2B customers.
Now, tax rules are applied **correctly** based on the customer type.

### Robots.txt configuration

The rendering of the `robots.txt` file has been changed to support custom `User-agent` blocks and the full `robots.txt` standard.
For a detailed guide on how to use the new features and extend the functionality, please refer to our documentation guide [Extend robots.txt configuration](https://developer.shopware.com/docs/guides/plugins/plugins/content/seo/extend-robots-txt.html).

### Scheduled Task for cleaning up corrupted media entries

A new scheduled task `media.cleanup_corrupted_media` has been introduced.
It detects and removes corrupted media records, such as entries created by interrupted or failed file uploads that have no corresponding file on the filesystem.

## API

### Add the possibility to specify indexer in context

When you want to specify which indexer should run, you can add the `EntityIndexerRegistry::EXTENSION_INDEXER_ONLY` extension to the context as follows:

```php
$context->addExtension(EntityIndexerRegistry::EXTENSION_INDEXER_ONLY,
    new ArrayEntity([
        ProductIndexer::STOCK_UPDATER // Only execute STOCK_UPDATER.
    ]),
);
```

When making a call to the Sync API, specify the required indexer in the header:

```bash
curl -X POST "http://localhost:8000/api/_action/sync" \
-H "indexing-only: product.stock" \
#...
```

## Core

### Automatic indexer execution for plugin migrations

The `IndexerQueuer` now runs automatically during plugin install, update, and uninstall events.
This ensures that registered indexers are executed when plugin migrations have run.

### Improved Store API OpenAPI documentation with field descriptions

The OpenAPI schema generator for Store API endpoints now includes descriptions for entity fields, making it easier for developers to understand the available fields and their purposes.

Additionally, available associations for each entity are now automatically listed in the OpenAPI operation descriptions, showing developers which relationships can be loaded.

To add descriptions to fields in your custom entity definitions, use the `setDescription()` method:

```php
(new ManyToOneAssociationField('group', 'customer_group_id',
    CustomerGroupDefinition::class, 'id', false))
    ->addFlags(new ApiAware())
    ->setDescription('Customer group determining pricing and permissions')
```

### Allow overwriting Doctrine wrapperClass on Primary/Replica setups

It's now possible to overwrite the `wrapperClass` of the `Doctrine\DBAL\Connection` instance.
This is useful if you want to use e.g. `Doctrine MySQL Comeback` to automatically reconnect if the MySQL connection is lost.

```bash
composer require facile-it/doctrine-mysql-come-back ^3.0
```

Then specify the `wrapperClass` in the `.env` file:

```
DATABASE_URL=mysql://root:root@database/shopware?driverOptions[x_reconnect_attempts]=5&wrapperClass=Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection
```

### Robots.txt parsing

A new `Shopware\Storefront\Page\Robots\Parser\RobotsDirectiveParser` has been introduced to parse `robots.txt` files. This new service provides improved error tracking and adds new events for better extensibility.
As part of this change, the constructor for `Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct` is now deprecated for string parameters. You should use the new parser to create a `ParsedRobots` object to pass to the constructor instead.

### new JWT helper

Added new `Shopware\Core\Framework\JWT\SalesChannel\JWTGenerator` and `Shopware\Core\Framework\JWT\Struct\JWTStruct` to build general structure for encoding and decoding JWT.

### Added PHP 8.5 polyfill

The new dependency `symfony/polyfill-php85` was added, to make it possible to already use PHP 8.5 features, like `array_first` and `array_last`

### Removal of old `changelog` handling

As we changed how we process and generate changelogs the "old" changelog files are no longer needed.
Therefore, we removed all the internal code used to generate and validate them.
The whole `Shopware\Core\Framework\Changelog` namespace was removed.
The code is not needed anymore, you should adjust the `RELEASE_INFO` and `UPGRADE` files manually instead.

### Deprecated the `\Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper`

Refection has significantly improved in particular since PHP 8.1, therefore the `Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper` was deprecated and will be removed in the next major release.
See below for the explicit replacements:

```diff
- $property = ReflectionHelper->getProperty(MyClass::class, 'myProperty');
+ $property = \ReflectionProperty(MyClass::class, 'myProperty');
```

```diff
- $method = ReflectionHelper->getMethod(MyClass::class, 'myMethod');
+ $method = \ReflectionMethod(MyClass::class, 'myMethod');
```

```diff
- $propertyValue = ReflectionHelper->getPropertyValue($object, 'myProperty');
+ $propertyValue = \ReflectionProperty(MyClass::class, 'myProperty')->getValue($object);
```

```diff
- $fileName = ReflectionHelper->getFileName(MyClass::class);
+ $fileName = \ReflectionClass(MyClass::class)->getFileName();
```

### New constraint to check for existing routes

The new constraint `\Shopware\Core\Framework\Routing\Validation\Constraint\RouteNotBlocked` checks if a route is available or already taken by another part of the application.

### Multiple payment finalize calls allowed

With the feature flag `REPEATED_PAYMENT_FINALIZE`, the `/payment-finalize` endpoint can now be called multiple times using the same payment token.
This behaviour will be the default in the next major release.
If the token has already been consumed, the user will be redirected directly to the finish page instead of triggering a PaymentException.
To support this behavior, a new `consumed` flag has been added to the payment token struct, which indicates if the token has already been processed.
Payment tokens are no longer deleted immediately after use. A new scheduled task automatically removes expired tokens to keep the `payment_token` table clean.

### Added sanitized HTML tag support for app snippets

Added sanitized HTML tag support for app snippets. App developers can now use HTML tags for better formatting within their snippets. The sanitizing uses the `basic` set of allowed HTML tags from the `html_sanitizer` config, ensuring that security-related tags such as `script` are automatically removed.

### App custom entity association handling

The behaviour creating associations with custom entities in apps changed.
Now an exception will be thrown if the referenced table does not exist, instead of creating a reference to the non-existing table.

To allow the schema updater to skip creating associations if the referenced table does not exist, improving flexibility and robustness during schema updates, a new optional attribute `ignore-missing-reference` was added to association types (`one-to-one`, `one-to-many`, `many-to-one`, `many-to-many`).

Example usage:
```xml
<one-to-many name="custom_entity" reference="quote_comment" ignore-missing-reference="true" store-api-aware="false" on-delete="set-null" />
```

### Translatable product manufacturer links

The `link` property of the product manufacturer entity is now translatable.

## Administration

### URL restrictions for product and category SEO URLs

When creating a SEO URL for a product or category, the URL is now checked for availability. Before it was possible to override existing URLs like `account` or `maintenance` with SEO URLs. Existing URLs are now blocked to be used as SEO URLs.

## Refactor filters for the newsletter recipients list.

We now use the `<mt-select>` instead `administration/src/module/sw-newsletter-recipient/component/sw-newsletter-recipient-filter-switch`.
Because of that, we deprecate these twig blocks:
* `sw_newsletter_recipient_list_sidebar_filter_status_not_set`
* `sw_newsletter_recipient_list_sidebar_filter_status_direct`
* `sw_newsletter_recipient_list_sidebar_filter_status_opt_in`
* `sw_newsletter_recipient_list_sidebar_filter_status_opt_out`

These blocks will be removed in v6.8.0.0 without replacement. Use the parent blocks instead.
We also deprecate
`administration/src/module/sw-newsletter-recipient/component/sw-newsletter-recipient-filter-switch` which will be removed with v6.8.0.0 and
`administration/src/module/sw-newsletter-recipient/page/sw-newsletter-recipient-list/index.js` which will be private in v6.8.0.0.

## Storefront

### Language selector twig blocks

New extensible Twig blocks `layout_header_actions_language_widget_content_inner` and `layout_header_actions_languages_widget_form_items_flag_inner` have been added to the language selector to allow custom flag implementations.

### `context.token` is no longer available in twig rendering context

The `context.token` variable is no longer available in twig rendering context to prevent potential security vulnerabilities. If you need to access the token, consider using alternative methods that do not expose it in the rendered HTML.
Usually inside the Twig storefront there is no need to handle the context token manually, as it is handled automatically via the session handling in the Storefront.


### Added specific `add-product-by-number` template
The `page_checkout_cart_add_product*` blocks inside `@Storefront/storefront/page/checkout/cart/index.html.twig` are deprecated and a new template `@Storefront/storefront/component/checkout/add-product-by-number.html.twig` was added.

Instead of overwriting any of the `page_checkout_cart_add_product*` blocks inside `@Storefront/storefront/page/checkout/cart/index.html.twig`,
extend the new `@Storefront/storefront/component/checkout/add-product-by-number.html.twig` file using the same blocks.

Change:
```
{% sw_extends '@Storefront/storefront/page/checkout/_page.html.twig' %}

{% block page_checkout_cart_add_product %}
    {# Your content #}
{% endblock %}
```
to:
```
{% sw_extends '@Storefront/storefront/component/checkout/add-product-by-number.html.twig' %}

{% block page_checkout_cart_add_product %}
    {# Your content #}
{% endblock %}
```

## Hosting & Configuration

### Sales Channel Replace URL Command

A new `sales-channel:replace:url` command was added to replace the url of a sales channel.
```bash
bin/console sales-channel:replace:url <previous_url> <new_url>
```

### Changed `CACHE_CONTEXT_HASH_RULES_OPTIMIZATION` feature flag to `CACHE_REWORK`

The `CACHE_CONTEXT_HASH_RULES_OPTIMIZATION` feature flag was renamed to `CACHE_REWORK` to better reflect its purpose, as more changes will be toggled by that flag, to enable the new cache behaviour.

To enable the new cache behaviour, set the `CACHE_REWORK` feature flag to `1` in your `.env` file:
Before:
```
CACHE_CONTEXT_HASH_RULES_OPTIMIZATION=1
```

Now:
```
CACHE_REWORK=1
```
To not break plugins that might check for the old flag unnecessarily, the old flag will be kept until the next major release, however, the flag has no effect anymore.

### Staging configuration

The disabled delivery check in `MailSender` now checks for the Staging Mode `core.staging`, the `shopware.staging.mailing.disable_delivery` configuration and the config setting `shopware.mailing.disable_delivery`.
Regardless of mode the config setting `shopware.mailing.disable_delivery` always allows disabling mail delivery.

## Critical fixes

### Product weight precision

The database column `product.weight` now uses `DECIMAL(15,6)` instead of `DECIMAL(10,3)` to keep gram-based measurements accurate when values are stored in kilograms.

# 6.7.4.0

### Plugin config default values

The default values for plugin config fields are now parsed according to the type of the field.
This means default values for `checkbox` and `bool` fields are parsed as boolean values, `int` fields are parsed as integer values, and `float` fields are parsed as float values.
Everything else is parsed as string values. With this the default values are now consistent based on the type of the field and the type does not depend on the actual value.
This makes it more consistent as otherwise the types could change when they are configured in the Administration.

### Deprecated SystemConfig exceptions

The exceptions

* `\Shopware\Core\System\SystemConfig\Exception\InvalidDomainException`
* `\Shopware\Core\System\SystemConfig\Exception\InvalidKeyException`
* `\Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException`

are now deprecated and will be removed in v6.8.0.0.
Use the respective factory methods in `\Shopware\Core\System\SystemConfig\SystemConfigException` instead.

### Deprecated SystemConfigService tracing methods

The methods `\Shopware\Core\System\SystemConfig\SystemConfigService::trace()` and `\Shopware\Core\System\SystemConfig\SystemConfigService::getTrace()` are deprecated and will be removed.
The tracing is not needed anymore since the cache rework for 6.7.0.0.
For now the methods are still available, but they do nothing.

### Add the correct interface to filterable price definitions

If a price definition should be filterable, explicitly implement the `Shopware\Core\Checkout\Cart\Price\Struct\FilterableInterface`, which defines the required `getFilter()` method.

## Storefront

### Vimeo and YouTube Cookie Consent Separation

With this change, Vimeo and YouTube videos now use separate cookie consent entries and load immediately when cookies are accepted, improving user experience and GDPR compliance.

### Cookie offcanvas links in dynamically loaded content

Links to open the cookie offcanvas that are loaded dynamically (e.g., within the navigation offcanvas) now work correctly.
The `CookieConfiguration` plugin now uses event delegation instead of direct event listeners.

If you have extended the `CookieConfiguration` plugin and override `_registerEvents()`, you may need to update your
implementation to use event delegation as well.

# 6.7.3.0

## Improvements

### Language handling

#### American English can be used in installer

American English can now be downloaded in the installer and can become the default shop language like any other language in Shopware.

#### Available languages can be managed from Shopware core

No plugin is needed anymore to install languages available from the Shopware translation platform.
The entire plugin has been built into the core.
Simply fetch and activate the language of your choice via the new bin/console commands.
Later, this feature will become available in the administration.

However, for any other language pack not available from the Shopware translation platform, you will still need a plugin.

You can fetch Shopware translations from the Shopware translation platform, which are stored on Github.
You can even help provide translations and use them in your shop a short time later!

Please note: As these are community-provided translations, we cannot guarantee that everything is translated 100% correctly.

Good news: The Language Pack plugin will continue to be maintained under our usual release policy.

Please see the [ADR](adr/2025-06-03-integrating-the-language-pack-into-platform.md) for more details.

#### Country-Agnostic Language Layer

Working with language codes in Shopware, such as en-GB (a combination of language and country), generally works well.
However, this approach can be quite maintenance-heavy: using multiple dialects, for example, British and American English, always leads to duplicated language snippets and can quickly become frustrating for translators.

To address this, we introduced an additional translation layer that reduces dialects to patch files, limiting duplication to only a small portion of the snippets.

Read the full story in this [ADR](adr/2025-09-01-adding-a-country-agnostic-language-layer.md). You can also find a detailed concept document for further reference.

### CMS / Shopping Experience

#### Block type labels

See the type of blocks directly when working with it as an editor.
This is especially useful if using third party plugins.
Thanks to @amenk!

https://github.com/shopware/shopware/pull/12334

#### 3D/canvas switching

Slider viewers are now rendered in respect to their visibility modus. This gives us a bit of more performance.
Thanks to @ffrank913 😉

https://github.com/shopware/shopware/pull/12642

#### Performance: Faster product category loading with a new index

Thanks to this pull request, queries on product.categories shall run ways faster than before: See https://github.com/shopware/shopware/pull/12657 by @vienthuong

#### Checkout & Promotions: More reliable shipping price matrix, credit notes, and promotion discount calculations

https://github.com/shopware/shopware/pull/12560 by @untilu29 actually fixes Shipping method cannot be applied to products below 1 EUR due to “Cart price from” default by @cramytech.

https://github.com/shopware/shopware/pull/12589 by @ennasus4sun fixes Credit notes are created cumulatively by @swagTKA.

https://github.com/shopware/shopware/pull/12603 by @socrec fixes Fixed Price delivery promotions cannot be excluded by janobi

#### 3D Viewer: Improved visuals with better camera distance and model placement

https://github.com/shopware/shopware/pull/12682 by ffrank913 fixes Incorrect model focus in SW6 standard CMS by himself

https://github.com/shopware/shopware/pull/12654 by ffrank913 fixes Incorrect frontend display of 3D glb files in SW6 standard CMS by MaximilianFo

### More tech updates

* Framework & API: Store-API cookie groups, new route exception handling, cleaner query parsing
* Platform ops / DX: Environment variable improvements, cache directory configurability, profiler disabled by default in production
* Build tooling: Admin build target updated to ES2023 (plugin authors should check compatibility)
* Deprecations / Breaking changes:
  * Removal of `controllerName` and `controllerAction` variables in templates
  * Deprecation of `Shopware\Core\System\SalesChannel\SalesChannel\SalesChannelContextSwitcher`
* Upgrade notes: DB migration for the new category index, admin build target upgrade, profiler defaults
