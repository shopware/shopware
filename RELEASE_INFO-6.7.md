# 6.7.15.0 (upcoming)

## Core

### `system:install` dispatches `SystemInstallCompletedEvent`

`Shopware\Core\Framework\Event\SystemInstallCompletedEvent` is dispatched after a successful `bin/console system:install`. The event exposes the CLI `Context`. Extensions can subscribe to run post-install work.

When Elasticsearch indexing is enabled and the cluster is reachable, the Elasticsearch bundle listens to this event and creates empty storefront indices and aliases. Storefront search after a fresh install no longer fails with `index_not_found_exception` because the alias is missing. Population stays a later `es:index` run.

### New document lifecycle business events

Two new events give extensions a hook into the document lifecycle without polling or fetching the document to discover its type, number, order and file:

- `document.generation.completed` (`Shopware\Core\Checkout\DocumentV2\Event\DocumentGeneratedEvent`) is dispatched when a document is generated or uploaded for an order. It exposes `documentId`, `orderId`, `orderVersionId`, `documentType` and `documentNumber`.
- `document.generation.deleted` (`Shopware\Core\Checkout\DocumentV2\Event\DocumentDeletedEvent`) is dispatched when a document is deleted, for both legacy and Document V2 documents. It exposes `documentId`, `orderId`, `orderVersionId`, `documentNumber` and `deletedAt`.

Both events are selectable as triggers in Flow Builder. `document.generation.completed` fires for both the legacy document pipeline (`Shopware\Core\Checkout\Document\Service\DocumentGenerator::generate()` and `::upload()`) and the Document V2 pipeline (`POST /_action/order/document-v2/create` and `POST /_action/order/document-v2/upload`); `document.generation.deleted` already covers both, since deletion goes through the shared `document` entity regardless of which pipeline created it.

### Customer imports validate customer number patterns

Customer import records whose `customerNumber` does not match the configured customer number range pattern for the resolved sales channel are now rejected and written to the invalid-records file. Adjust the imported customer numbers or the number range pattern before retrying the import.

Custom number range increment storages can implement `AbstractIncrementStorage::increaseToAtLeast()` to raise an existing increment state without lowering higher values.

### Server-side cookie consent logging (experimental)

The built-in cookie banner now logs every consent decision server-side, so shop operators can demonstrate that consent was obtained, as required by GDPR Recital 42. The feature is active by default and can be switched off per shop. Its routes, database schema and events are experimental until 6.8.0 and not yet covered by the backwards compatibility promise, so they can still change in a patch release.

- Every "accept all", "accept required only", and custom-selection interaction with the cookie banner is sent (fire-and-forget via `navigator.sendBeacon`) to the new storefront route `POST /cookie/consent-log`, which proxies to the new Store API route `POST /store-api/cookie-consent-log`.
- The client only reports raw facts: the action, the names of the cookies the visitor ticked (`acceptedCookies`), and the hash of the configuration it displayed (`renderedConfigHash`). The per-group verdict is derived server-side against the configuration the server holds, so it cannot be shaped by the client.
- Decisions are stored anonymously in the new `cookie_consent_log` table: action, per-group verdict (`group_decisions`), accepted cookie names (`accepted_cookies`), sales channel, language, both configuration hashes, and timestamp. No IP addresses, session IDs, or other visitor identifiers are stored.
- `group_decisions` distinguishes `accepted`, `partial`, and `rejected` per cookie group. A group counts as `accepted` only when every cookie the visitor could tick in it was accepted, so a granular selection is never recorded as broader consent than was given.
- `server_config_hash` always has a matching snapshot row; `rendered_config_hash` is the unverified hash the client reported and is `NULL` when no configuration was displayed, e.g. when only the cookie bar buttons were used. A mismatch between the two is queryable instead of silent.
- A snapshot of the cookie banner configuration is stored once per configuration hash in the new `cookie_consent_config_version` table, preserving what the banner looked like when consent was given.
- Both tables are readable through the Admin API (e.g. `POST /api/search/cookie-consent-log`) for compliance exports.
- Read access can be granted without making someone an administrator: the new "Cookie consent logging" permission under Settings in Users & Permissions grants `cookie_consent_log:read` and `cookie_consent_config_version:read`. It offers a viewer role only, both tables reject every write from an API scope, including deletes.
- The new event `Shopware\Core\Content\Cookie\Event\CookieConsentLoggedEvent` is dispatched for every logged decision. It is hookable: apps can subscribe to it as the webhook event `cookie.consent.logged`, gated by the `cookie_consent_log:read` privilege. The payload mirrors a log row and is anonymous.
- Both routes are rate limited per client IP (`cookie_consent_log`, 60 requests per 60 seconds, `sliding_window`), because the endpoint is anonymous and every accepted request inserts a row. The IP is only used as the limiter key and is never stored with the decision.
- Logging can be switched off per sales channel with the `core.cookieConsent.logEnabled` system config (default: enabled), in the admin under Settings > Basic information, in the new "Cookie consent logging" card. Turn it off for a sales channel whose consent is handled by a third-party manager. Decisions that were already recorded are kept and still expire through the retention period. While it is off the routes still answer `204`, and the storefront of that sales channel stops sending the beacon at all.
- The new daily scheduled task `cookie_consent_log.cleanup` deletes log entries older than the retention period, configurable via the `core.cookieConsentRetention.days` system config (default: 120 days, `0` deletes immediately, negative values disable the cleanup), and removes banner snapshots that are no longer referenced. Operators can manage it in the admin under Settings > Basic information, in the new "Cookie consent retention" card. The cleanup deletes across the whole installation, so unlike the logging switch this value is not per sales channel.
- The Store API `/store-api/cookie-groups` response now additionally exposes the translation-independent `technicalName` of each cookie group.

Because no visitor identifier is stored, the log is process-level evidence: it demonstrates that consent was collected, under which banner configuration and when, but it cannot identify an individual data subject.

Shops using a third-party consent manager instead of the built-in cookie banner are not affected. (shopware/shopware#15513)
### `JsonField::addPropertyMapping()` for entity extensions

`Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField` now has `addPropertyMapping()`. Plugins can call it from `EntityExtension::modifyFields()` to extend an existing JSON schema, for example to add another entity key to a structured `hitCount` map. The field collection passed to `modifyFields()` is keyed by property name, so `$collection->get('hitCount')` returns the field.

```php
public function modifyFields(FieldCollection $collection): void
{
    $hitCount = $collection->get('hitCount');
    if (!$hitCount instanceof JsonField) {
        return;
    }

    $hitCount->addPropertyMapping(new JsonField('landing_page', 'landing_page', [
        new IntField('maxSuggestCount', 'maxSuggestCount'),
        new IntField('maxSearchCount', 'maxSearchCount'),
    ]));
}
```

### App payment method translations are preserved

Installing or updating an app no longer overwrites existing payment method name and description translations. Manifest texts are only applied to languages without a translation.

### New event to register product listing sortings at runtime

`Shopware\Core\Content\Product\Events\ProductListingCollectSortingEvent` is dispatched while the product listing, search and suggest criteria are built, before the requested sorting is resolved. Add a `ProductSortingEntity` to `$event->getSortings()` to make it selectable and applicable at runtime:

```php
public static function getSubscribedEvents(): array
{
    return [ProductListingCollectSortingEvent::class => 'addSorting'];
}

public function addSorting(ProductListingCollectSortingEvent $event): void
{
    $event->getSortings()->add($mySorting);
}
```

## API

### Store API context token response header is restricted on cacheable reads

Store API responses no longer echo the request `sw-context-token` header on cacheable reads when `CACHE_REWORK` or `v6.8.0.0` is active. The response header is returned by endpoints that provide or bootstrap shopper state, for example reading or switching context, login, logout, registration, password change, guest-order login, adding cart items, and context gateway login/register commands. Clients should keep using their existing token unless a response explicitly provides a `sw-context-token`.
### Dedicated error code for invalid child line item quantity

`CartException::invalidChildQuantity()` now returns the error code `CHECKOUT__CART_INVALID_CHILD_LINE_ITEM_QUANTITY` (constant `CartException::CART_INVALID_CHILD_LINE_ITEM_QUANTITY_CODE`) instead of reusing `CHECKOUT__CART_INVALID_LINE_ITEM_QUANTITY`. Previously both `invalidChildQuantity()` and `invalidQuantity()` shared the same error code, so the shared storefront message `The quantity (%quantity%) is incorrect.` was rendered with an empty `%quantity%` placeholder for the child quantity case (`invalidChildQuantity()` never provided that parameter). If you match on the previous error code to detect invalid child quantities, switch to the new code.

## Administration

### Shipping prices can be linked to the tax rate

The shipping price matrix now renders `sw-price-field` per currency instead of two separate number fields. Gross and net can be linked with the lock button, and a linked net price is calculated from the gross price using the shipping method's tax rate. New shipping prices are linked by default; existing ones keep their stored state.

Extensions that override the `sw_settings_shipping_price_matrix_price_grid_currencies_list` block or style the removed `.sw-settings-shipping-price-matrix__price-input` class must be adjusted to the `sw-price-field` markup. The gross and net input `name` attributes are unchanged.
### Admin UI shell rework (sidebar, top bar, smart bar)

The Administration shell — main menu sidebar, top bar, search bar, and smart bar — has been modernized and improved in behavior and responsiveness. Extensions that override these areas via Twig blocks, style them via the removed CSS classes, or rely on the previous color props need to adapt.

#### Removed Twig blocks

The following blocks have been removed and can no longer be extended:

- `src/app/component/structure/sw-admin-menu/sw-admin-menu.html.twig`
  - `sw_admin_menu_toggle_sidebar`
  - `sw_admin_menu_toggle_sidebar_icon`
  - `sw_admin_menu_toggle_sidebar_text`
  - `sw_admin_menu_user_actions`
  - `sw_admin_menu_user_actions_label`
  - `sw_admin_menu_user_actions_list`
- `src/app/component/structure/sw-admin-menu-item/sw-admin-menu-item.html.twig`
  - `sw_admin_menu_item_arrow_indicato` (sic)
  - `sw_admin_menu_item_arrow_indicator`
  - `sw_admin_menu_item_external_arrow_indicato` (sic)
  - `sw_admin_menu_item_external_icon`
  - `sw_admin_menu_item_external_text`
- `src/app/component/base/sw-version/sw-version.html.twig`
  - `sw_version_name`
  - `sw_version_name_text`
  - `sw_version_status`
  - `sw_version_status_badge`
- `src/app/component/structure/sw-search-bar/sw-search-bar.html.twig`
  - `sw_search_bar_version_display`
- `src/module/sw-sales-channel/component/structure/sw-sales-channel-menu/sw-sales-channel-menu.html.twig`
  - `sw_sales_channel_menu_context_button_collapsed`

#### Repurposed block: `sw_admin_menu_user_actions_items`

The user menu in the sidebar footer became an `mt-action-menu` dropdown. The block `sw_admin_menu_user_actions_items` still exists, but its content now renders inside `mt-action-menu` instead of a `<ul>` navigation list. Overrides that add `<li><router-link>` entries produce broken markup inside the dropdown and need to render `mt-action-menu-group` / `mt-action-menu-item` elements instead:

```twig
{% block sw_admin_menu_user_actions_items %}
    {% parent %}

    <mt-action-menu-group>
        <mt-action-menu-item
            icon="regular-cog"
            @click="onMyAction"
        >
            My entry
        </mt-action-menu-item>
    </mt-action-menu-group>
{% endblock %}
```

#### Restructured blocks in `sw-page.html.twig`

- A new `sw_page_top_bar` block wraps the top bar. `sw_page_top_bar_actions` is no longer nested inside `sw_page_search_bar` — overrides that copied the previous markup render the top bar actions twice.
- The root element of `sw_page_smart_bar` changed from a `<template>` to a `<div class="sw-page__smart-bar">`.

#### Smart bar back button styling removed

The `.smart-bar__back-btn` CSS ruleset was removed from `sw-page.scss`. `#smart-bar-back` slot overrides that render a bare `<router-link class="smart-bar__back-btn">` with icons lose their styling. Migrate to the pattern the default back button uses:

```twig
<template #smart-bar-back>
    <router-link
        v-slot="{ href, navigate }"
        :to="myBackRoute"
        custom
    >
        <mt-button
            is="a"
            class="smart-bar__back-btn"
            variant="secondary"
            size="default"
            square
            :href="href"
            :aria-label="$t('global.sw-page.backButton')"
            @click="navigate"
        >
            <mt-icon
                name="solid-long-arrow-left"
                size="12px"
            />
        </mt-button>
    </router-link>
</template>
```

#### Removed snippets and static asset

- `global.sidebar.buttonCollapse` has been removed.
- `sw-extension.sw-extension-app-module-error-page.error.phrase` and `.error.info` have been removed; the app module error page uses `mt-empty-state` with the new `.error.description` snippet.
- The static asset `static/img/error-pages/app-error.svg` has been removed. External URLs pointing to it return a 404.

#### Extension SDK: `ui.sidebar.close()` closes asynchronously

Closing a sidebar via the Extension SDK now plays a close animation (~400 ms) before the sidebar deactivates, instead of removing it immediately. With `prefers-reduced-motion` the sidebar still closes without delay. Do not rely on the sidebar being gone synchronously after calling `close()`.

#### `sw-page` and `sw-meteor-page` are CSS containers

Both page components declare `container-type: inline-size`. This creates a new containing block and stacking context: plugin content inside a page that uses `position: fixed` is now positioned relative to the page container instead of the viewport, and `z-index` values no longer compete with elements outside the page.

#### Color props without effect

- `sw-search-bar`: `entitySearchColor` and the `entityIconColor` prop of `sw-search-bar-item` are only applied while the user set the "Module colors" preference to "Colored" (see below). By default search results use the standard icon colors.

#### Optional module icon colors

The main menu and the search bar no longer color their icons by the `color` of the registered module. Users who prefer the previous look can switch the icons back to their module color with the "Module colors" setting in their profile settings (Profile settings > User interface). It defaults to "Neutral" and is stored per user in the `core.userModuleIconColors` user configuration.

The `color` property of `Module.register()` is unchanged and keeps feeding these icons, so extensions do not need to adapt.

### Individual promotion codes are released again when their promotion line item is deleted

Deleting a promotion line item from an order (or deleting the order) now releases the redeemed individual promotion code, so the customer can use it again. This restores the 6.6 behaviour that was lost in the 6.7 rewrite of `PromotionRedemptionUpdater`: since 6.7.0.0 a used individual code stayed permanently redeemed even after a merchant removed the promotion from the order — a common workflow when a payment fails and the order is edited, which previously forced merchants to generate and send a new code. The release is scoped to codes whose redemption payload references the order the line item is deleted from; the promotion usage counters were already recalculated correctly and are unchanged.

### Bulk operations in the My Extensions listing

The "My Extensions" listing can now act on several extensions at once instead of one card at a time, which noticeably speeds up maintaining shops with many extensions. Selecting one or more extensions replaces the listing controls with a bulk actions bar.

The bar offers the same actions already available per card:
- **Install**, **activate**, **deactivate**, **update**, and **uninstall** for all selected extensions in one step.
- Each action is enabled only when it applies to at least one selected extension (for example, *activate* counts only "installed but inactive" extensions) and shows how many of the selection it affects.
- All actions respect the existing `system.plugin_maintain` permission and the runtime extension-management setting, exactly like the single-card actions.

The listing reloads once after the batch finishes rather than after every individual extension. With nothing selected, the listing behaves exactly as before, so the feature is fully opt-in.

### Native-setup editor support comes from the extension tooling

Editor support for native-setup authoring is now generated by the extension tooling instead of copied by hand. Run `composer admin:setup-extension-tooling` (or `bin/console administration:setup-extension-tooling` in a Composer install): the generated ESLint config declares the compile-time macro globals (`swDefinePublic`, `swDefineOverride`, `useSwPreviousState`, `useSwProps`, `useSwContext`) and enables the `sw-core-rules/valid-shopware-setup` and `sw-core-rules/native-setup-filename` guards, and the generated type surface carries the macro declarations so they type-check.

The workspace templates in `build/vue-setup-transform/templates/custom-plugin-workspace` are removed with it. They imported `eslint-plugin-vue` and `@typescript-eslint/parser` through explicit paths into the Administration's `node_modules`, so a dependency bump broke every copied workspace at once. If you copied `eslint.config.mjs` to `custom/eslint.config.mjs` or `plugin-tsconfig.json` to `custom/plugins/<PluginName>/tsconfig.json`, delete them and run the setup command instead.

## Storefront

### Passive privacy notices without a checkbox

Storefront privacy notices now use passive wording when `core.loginRegistration.requireDataProtectionCheckbox` is disabled. Contact and newsletter forms use the privacy-only snippet keys `contact.privacyNoticeTextModal` and `contact.privacyNoticeInformation`, while forms that include the terms of service use `general.privacyNoticeTextModal` and `general.privacyNoticeInformation`. Themes and custom snippet sets can override the new `*.privacyNoticeInformation` keys to adjust the non-blocking notice.

The regular registration action now uses `account.registerSubmit`, while the checkout registration and guest-order authentication use `checkout.registerSubmit`.

### Semantic footer markup

With v6.8.0.0 the footer (`layout/footer/footer.html.twig`) will use semantic elements.

- Collapse section headlines will become `<h2>` instead of `<div role="heading">`.
- Footer columns wrapper will become `<ul>` instead of `<div role="list">` (`role="list"` is kept so Safari/VoiceOver still exposes it as a list).
- Footer column will become `<li>` instead of `<div role="listitem">`.

### Clear message when adding a second code of the same promotion

Applying a second (individual) code that belongs to a promotion already present in the cart no longer fails silently or shows a generic error. The redundant code is dropped and the customer is informed with a dedicated notice, because a promotion can only be applied once per order. The message uses the new snippet key `checkout.promotion-not-eligible-already-added`, which theme and translation developers can override.

### Postal codes are validated in the browser

The country `<option>` rendered by `component/address/field/address-country-field.html.twig` now carries `data-zipcode-pattern` and `data-check-zipcode-pattern`, next to the existing `data-zipcode-required`. `CountryStateSelectPlugin` reads them and applies the pattern to every `[data-input-name="zipcodeInput"]` field, so an invalid postal code is reported on submit instead of only after the rest of the form passes.

If your theme overrides `component_address_field_country` and renders its own `<option>` markup, add both attributes to keep postal code validation working in the browser. Server-side validation is unchanged.

### Essential characteristics render select, entity and price custom fields

Custom fields of the types `select`, `entity` and `price` are now rendered when they are part of a product's essential characteristics. Their line item payload gained an optional `display` key next to the untouched `content`:

```
lineItem.payload.features[].value = { id, type, content, display }
```

`display` holds a list of resolved option or entity labels for `select` and `entity`, and the price of the current currency and tax state as a float for `price`. It is only present on line items built after the update, so templates overriding `component/product/feature/types/feature-custom-field.html.twig` must treat it as optional. A characteristic that cannot be resolved is dropped from the payload, and `component/product/feature/item.html.twig` no longer emits an empty list item for a characteristic its template renders nothing for.

# 6.7.14.0

## Features

### New "Automation" administration menu entry

Rule Builder and Flow Builder are now reachable from a dedicated top-level "Automation" menu entry. The existing "Settings > Automation" entries are unchanged.

### New app script hook `cookie-group-collect`

Apps can now modify or remove cookie consent groups and entries with an app script under `Resources/scripts/cookie-group-collect/`. The hook exposes the collected `cookieGroups` collection and the current sales channel context, and provides the `services.repository`, `services.store` and `services.config` script services. Scripts run after cookies from plugins and app manifests were collected, so an app can, for example, declare its cookies in the manifest and remove them when the related payment method is not active in the current sales channel — with full backwards compatibility, since older Shopware versions simply ignore scripts for unknown hooks.

### Order state history records where a state change came from

`state_machine_history` entries now carry a `sourceType` field holding the type of the API context source that triggered the transition (`admin-api`, `sales-channel` or `system`). A custom `ContextSource` contributes whatever its public `$type` property holds. The order detail page and the status history modal use it to show state changes that a customer made in the storefront as "Customer" instead of "System", so a cancellation by the customer can be told apart from one made by a plugin or an integration.

State changes that an administrator performs in a sales channel context, for example while creating an order in the Administration, are now attributed to that administrator instead of to the system.

## API

### Added experimental Store API snippet endpoint

Added new experimental Store API route `GET /store-api/snippet` (not part of the backwards compatibility promise yet, planned to be stable with v6.8.0), which returns the fully resolved snippets (translations) for the current sales channel context as a list of sets, each carrying a flat key-value map (`{"account.loginTitle": "Log in"}`). By default the list contains one set for the language of the `sw-language-id` header; the language fallback chain is merged server-side, so values are never null. The optional `prefixes` query parameter limits the result to namespace prefixes (e.g. `?prefixes=checkout,account`, at most 50 distinct prefixes per request), the optional `languageIds` query parameter fetches multiple sales channel languages in one request. Responses carry an `ETag` header and support `If-None-Match` revalidation, so headless frontends (e.g. Composable Frontends) can bake translations at build time and revalidate them cheaply at runtime.
### Number range admin action endpoints now require ACL privileges

Three admin action endpoints that previously only required authentication now enforce ACL privileges. Requests with tokens lacking the privilege receive a `403` with `FRAMEWORK__MISSING_PRIVILEGE_ERROR`:

* `GET /api/_action/number-range/reserve/{type}/{salesChannelId}` requires `number_range:read`. Without `preview=1` this endpoint permanently advances the number range state, so it was possible for any authenticated backend account to consume invoice, order, delivery-note and credit-note numbers and create gaps in the sequence.
* `GET /api/_action/number-range/{numberRangeId}/preview-pattern` and the deprecated `GET /api/_action/number-range/preview-pattern/{type}` require `number_range:read`.

Administration users are not affected: `number_range:read` is already part of the "Products viewer" and "Number ranges viewer" permissions, it is now also part of the "Orders editor" and "Customers creator" permissions — these are the roles whose users reserve document, order and customer numbers — and a migration grants it to existing roles that already hold one of those permissions. Integrations and API clients with manually assigned privilege lists must add `number_range:read` to their ACL role.

### Added new shop setting endpoint

Added new Store API route `GET /store-api/shop-settings`, which exposes the UI- and validation-relevant, non-sensitive subset of the system configuration (grouped into `general`, `loginRegistration`, `cart`, `listing` and `newsletter`) resolved for the current sales channel, so headless frontends (e.g. Composable Frontends) can render the shop consistently with the administration settings.
### Cache information includes registered indexers

`GET /api/_action/cache_info` now returns an `indexers` map containing the registered normal-refresh indexers and their optional child updaters. Administration clients can use this metadata when offering cache-index refresh controls; post-update-only indexers are excluded.

### Order recalculation and conversion endpoints now require ACL privileges

Thirteen admin checkout endpoints that previously only required authentication now enforce ACL privileges. Requests with tokens lacking the privilege receive a `403` with `FRAMEWORK__MISSING_PRIVILEGE_ERROR`:

* `POST /api/_action/order/{orderId}/recalculate`, `/product/{productId}`, `/creditItem`, `/lineItem`, `/promotion-item`, `/toggleAutomaticPromotions` and `/applyAutomaticPromotions`, plus `PATCH /api/_proxy/modify-shipping-costs`, `/api/_proxy/disable-automatic-promotions` and `/api/_proxy/enable-automatic-promotions`, require `order:update`.
* `POST /api/_action/order-address/{orderAddressId}/customer-address/{customerAddressId}` and `POST /api/_action/order/{orderId}/order-address` require `order_address:update`.
* `POST /api/_action/order/{orderId}/convert-to-cart/` requires `order:read`.

Administration users are not affected: `order:update` and `order_address:update` are part of the "Orders editor" permission that already gates every one of these actions in the order detail page, `order:read` is part of "Orders viewer", and the order creator role depends on both. Integrations and API clients with manually assigned privilege lists must add the respective privilege to their ACL role.
### User uniqueness validation endpoints now require user read access

The `POST /api/_action/user/check-email-unique` and `POST /api/_action/user/check-username-unique` endpoints now require the existing `user:read` privilege. Integrations and API clients that call these endpoints must add this privilege to their ACL role.

### Message queue admin endpoints now require ACL privileges

Three admin endpoints that previously only required authentication now enforce ACL privileges. Requests with tokens lacking the privilege receive a `403` with `FRAMEWORK__MISSING_PRIVILEGE_ERROR`:

* `POST /api/_action/message-queue/consume` and `POST /api/_action/scheduled-task/run` require the new `system:queue:process` privilege.
* `GET /api/_action/scheduled-task/min-run-interval` requires the existing `scheduled_task:read` privilege.

Administration users are not affected: both privileges are granted to every authenticated Administration user at runtime, because these endpoints back the admin worker that processes the message queue in every admin session. Integrations and API clients calling these endpoints must have the respective privilege added to their ACL role — the runtime defaults apply to Administration users only. External workers should keep using the `bin/console messenger:consume` and `scheduled-task:run` CLI commands, which are unaffected.

### Plugin filesystem metadata is read-only through the Admin API

The `plugin.path` and `plugin.managedByComposer` fields can no longer be created or changed through generic Admin API writes. Plugin discovery and extension management continue to maintain these values automatically. Integrations must not write plugin filesystem metadata directly.

### SEO admin action endpoints now require ACL privileges

Four admin action endpoints that previously only required authentication now enforce ACL privileges. Requests with tokens lacking the privilege receive a `403` with `FRAMEWORK__MISSING_PRIVILEGE_ERROR`:

* `PATCH /api/_action/seo-url/canonical` requires `seo_url:update`.
* `POST /api/_action/seo-url/create-custom-url` requires `seo_url:create`.
* `POST /api/_action/seo-url-template/context` and `GET /api/_action/seo-url-template/default/{routeName}` require `seo_url_template:read`.

Administration users are not affected: `seo_url:update` is now part of the "Products editor", "Categories editor", and "Landing pages editor" permissions in the role editor — these are the roles whose users write canonical URLs when saving — and a migration grants it to existing roles that already hold one of those permissions. The template privileges are already part of the system configuration permission that the SEO settings page requires. Integrations and API clients with manually assigned privilege lists must add the respective privilege to their ACL role.

### Increment and queue-stats admin endpoints now require ACL privileges

Seven admin endpoints that previously only required authentication now enforce ACL privileges. Requests with tokens lacking the privilege receive a `403` with `FRAMEWORK__MISSING_PRIVILEGE_ERROR`:

* `POST|GET /api/_action/increment/{pool}`, `POST /api/_action/decrement/{pool}`, `POST /api/_action/reset-increment/{pool}`, and `DELETE /api/_action/delete-increment/{pool}` require the new `increment:manage` privilege.
* `GET /api/_info/queue.json` and `GET /api/_info/message-stats.json` require the existing `message_queue_stats:read` privilege.

Administration users are not affected: `increment:manage` is granted to every authenticated Administration user at runtime (the endpoints back module-usage tracking, which runs in every admin session), and `message_queue_stats:read` already is such a default privilege. Integrations and API clients calling these endpoints must have the respective privilege added to their ACL role — the runtime defaults apply to Administration users only.

### Media action routes now enforce ACL privileges

The Admin API media action routes now enforce their corresponding ACL privileges. Clients must have `media:create` to upload new media, upload from a URL, or create an external media link; `media:update` to upload content to existing media or rename media; `media_thumbnail:create` or `media_thumbnail:delete` to add or remove external thumbnails; and `media:read` to use the media filename lookup route.

The V2 upload and upload-from-URL routes already required `media:create` through their media repository write, and the filename lookup route already required `media:read` through its repository query. The external-link, legacy upload and rename, and external-thumbnail add and delete routes now enforce permissions that their system-scoped DAL writes did not previously require. Integrations and users that call those routes must update their ACL role.

### Template rendering endpoints require update privileges

The `POST /api/_action/product-export/preview` and `POST /api/_action/product-export/validate` endpoints now require the `product_export:update` ACL privilege. The `POST /api/_action/mail-template/simulate` endpoint now requires `mail_template:update`. Admin API integrations and users that use these endpoints must be granted the respective existing privilege.

### Admin action endpoints now require ACL privileges

Four admin action endpoints that previously only required authentication now enforce ACL privileges. Requests with tokens lacking the privilege receive a `403` with `FRAMEWORK__MISSING_PRIVILEGE_ERROR`:

* `POST /api/_action/sso/invite-user` requires the existing `user:create` privilege.
* `POST /api/app-system/shop-id/change` requires the new `system:app:change` privilege.
* `POST /api/_action/trigger-event/{eventName}` requires the new `flow:dispatch` privilege.
* `POST /api/_action/extension-sdk/run-action` requires `app.all` or the app-specific `app.{appName}` privilege.

The new privileges are part of the existing "Plugin maintain" (`system:app:change`) and "Flow editor" (`flow:dispatch`) permissions in the Administration role editor, and a migration grants them to roles that already hold those permissions — existing admin users keep access without manual changes. Integrations calling these endpoints must have the respective privilege added to their ACL role.

### `sw-expect-packages` is rejected on endpoints that do not require authentication

The `sw-expect-packages` header is no longer evaluated on API endpoints that do not require authentication, because the failure messages disclose the installed versions of Shopware and its dependencies. Sending it to such an endpoint now returns `417` with the new error code `FRAMEWORK__API_EXPECTATION_NOT_SUPPORTED` instead of evaluating the constraint. Affected endpoints include `GET /api/_info/health-check`, `POST /api/oauth/token`, `GET /api/app-system/shop/verify`, and the `POST /api/_action/user/user-recovery` routes.

Send the header with an authenticated Admin API request, where the behaviour is unchanged: a violated constraint still returns `417` with `FRAMEWORK__API_EXPECTATION_FAILED` and the installed version. Clients that set the header as a default on their HTTP client must remove it from unauthenticated calls — most importantly from the token request, which otherwise fails before the token is issued. Requests that do not send the header are unaffected.

### Store API schema documents cart totals as a separate `CartPrice` component

The Store API OpenAPI schema previously documented item prices and cart totals as one `CalculatedPrice` component, which marked the cart-level fields `netPrice`, `positionPrice`, `rawTotal`, and `taxStatus` as required on item prices such as `product.calculatedPrice` and `lineItem.price`. The schema now contains a dedicated `CartPrice` component used for `cart.price` and `order.price`, while `CalculatedPrice` only documents the fields item prices actually contain. The `taxStatus` enum also includes the previously missing `gross` value. API responses are unchanged; only clients generated from the schema are affected and now match the actual payloads.

### Store API no longer offers shipping methods without a usable price

`onlyAvailable=1` no longer returns active shipping methods whose prices cannot resolve a cost: an empty matrix, or rows that all lack currency values. One usable row is enough. Requests without the flag are unchanged.
### Sales channel language list validation compares against the incoming default language

Assigning a new `languageId` to a sales channel and removing the previous default language from its `languages` list in the same write is now accepted. It previously failed with `SYSTEM__CANNOT_DELETE_DEFAULT_LANGUAGE_ID`, and the two steps had to be sent as separate requests.

Removing the language that the same write assigns as the new default is now rejected with that error code instead of being applied. Such a write previously succeeded and left the sales channel with a default language that was missing from its language list.

## Core

### Document V1/V2 file compatibility

Document V1 and Document V2 can now open and download each other's files, including legacy files in the V2 archive download.

### An active shipping method must keep at least one usable price

Removing, reassigning or emptying the last usable `shipping_method_price`, or activating a method without one, now returns a `400` (`active_shipping_method_without_price`). Creating a method without prices still works. To remove a matrix, deactivate the method in an earlier request first.

Replace a matrix in a single request, so the method is never priceless in between:

```json
[
  { "key": "delete-prices", "entity": "shipping_method_price", "action": "delete", "payload": [{ "id": "…" }] },
  { "key": "write-prices", "entity": "shipping_method_price", "action": "upsert", "payload": [{ "id": "…", "shippingMethodId": "…", "calculation": 1, "quantityStart": 0, "currencyPrice": [{ "currencyId": "…", "net": 0, "gross": 0, "linked": false }] }] }
]
```

### E-invoice line positions state the correct price base quantity

ZUGFeRD invoices previously wrote the product's purchase unit (`product.purchaseUnit`, the package content used for base price display) as the item price base quantity (BT-149). Recipients validating against EN16931 saw `PEPPOL-EN16931-R120` violations for every line whose product has a purchase unit other than 1, and Peppol access points may have rejected such invoices. Line positions now always state a base quantity of 1, matching the per-unit item net price. Additionally, the item net price (BT-146) is now written with 4 decimals instead of 2, so the line amount calculation stays within the rule's rounding tolerance for higher quantities.
### New shop settings route classes
- Added `Shopware\Core\System\SystemConfig\SalesChannel\AbstractShopSettingsRoute` as a decoratable extension point.
- Added `Shopware\Core\System\SystemConfig\SalesChannel\ShopSettingsRoute`.
- Added `Shopware\Core\System\SystemConfig\SalesChannel\ShopSettingsRouteResponse` and the structs `ShopSettings`, `ShopGeneralSettings`, `ShopLoginRegistrationSettings`, `ShopCartSettings`, `ShopListingSettings`, `ShopNewsletterSettings` in the same namespace.
### Plugins can customize version cleanup

Plugins can subscribe to the new `CleanupVersionEvent` to protect version records from scheduled cleanup. The event provides the cleanup cutoff through `getCleanupTime()`, allowing plugins to apply retention rules consistently with the scheduled cleanup task.
### Force thumbnail regeneration and deletion via `--force`

The `media:generate-thumbnails` command now accepts a `--force` (`-f`) option that regenerates thumbnails for all configured sizes even when a thumbnail already exists — for example after changing the thumbnail quality or sizes of a media folder. The option works with both synchronous and `--async` execution. Previously, existing thumbnails were always skipped.

Note that regenerated thumbnails are written to the same physical path, so their URLs do not change: browsers and CDNs may keep serving the previously cached files until their cache expires or is invalidated manually.

The `media:delete-local-thumbnails` command also accepts a new `--force` (`-f`) option that deletes all thumbnail records and files even when remote thumbnails are disabled — previously the command refused to run in that case. The command now removes the whole physical thumbnail directory, which also cleans up orphaned files without a database record (e.g. left behind after their media was uploaded again), and prints the number of deleted files. Note that the storefront is missing thumbnails until they are regenerated; prefer `media:generate-thumbnails --force` to replace them without such a gap.

A new `--orphans` (`-o`) option deletes only those orphaned files. Referenced thumbnails and their records are kept, so this cleanup is safe in every setup and works regardless of the remote thumbnail configuration. The two options cannot be combined.

`ThumbnailService::updateThumbnails()` accepts a matching optional `$force` argument; classes overriding this method must add the parameter with Shopware 6.8 (see `UPGRADE-6.8.md`).
### New `Criteria::resetFields()` and `Criteria::resetExcludedFields()`

`Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::resetFields()` drops an allowlist added via `addFields()`, `Criteria::resetExcludedFields()` drops a denylist added via `excludeFields()`. Both selections are mutually exclusive, so the new methods also allow switching a criteria from one to the other.

They affect the database read, unlike `includes` and `excludes`, which only shape the API response.

### Failed payment mail flow

Shopware now ships a default Flow Builder flow, flow template, and mail template for `state_enter.order_transaction.state.failed`.

### GARAN commercial guarantee label and EU legal guarantee notice

- Products get a new `guaranteeMonths` field for an optional commercial durability guarantee beyond the statutory two years (must be empty, or a half-year value greater than 24 months).
- New Store API route `GET /store-api/product/{productId}/garan-label` renders the EU-harmonised GARAN label as SVG (full and nested variants) for products with a manufacturer and complete label data.
- New Twig filters `sw_garan_label`, `sw_garan_label_nested`, `sw_garan_label_data_uri`, `sw_garan_label_nested_uri`, `sw_garan_label_duration` render the label as inline SVG or as a base64 `data:` URI — the latter for mail clients, which strip inline `<svg>` markup.
- Separately, new Store API route `GET /store-api/legal-guarantee-notice` renders the EU-harmonised statutory legal guarantee notice, translated into all 24 official EU languages, toggleable via the new `core.cart.showLegalGuaranteeNotice` system config, and exposed via `sw_legal_guarantee_notice` / `sw_legal_guarantee_notice_link` Twig filters.
- Both labels/notices are wired into the storefront (checkout confirmation, buy-widget, cart line items) and into the Administration product detail page (new guarantee form).
- The `order_confirmation_mail` template is updated to include both the GARAN label (as a data URI) and the legal guarantee notice link. **This update only applies to shops whose order confirmation mail template is still the unmodified system default** — i.e. new installations, and existing shops that never edited that template. Merchants who have customized their order confirmation mail template must add `{{ nestedItem.productId|sw_garan_label_nested_uri(context) }}` and `{{ context.languageId|sw_legal_guarantee_notice_link }}` to their template manually if they want these notices.

### Category and SEO URL indexing cause fewer database deadlocks

Concurrent category writes through the Sync API frequently hit InnoDB deadlocks in the SEO URL and child-count updaters (`1213 Deadlock found when trying to get lock`). Three changes reduce the lock footprint while keeping the default `REPEATABLE READ` isolation: the `seo_url` table is written with `INSERT ... ON DUPLICATE KEY UPDATE` instead of `REPLACE INTO`, so a colliding row is updated in place — keeping its `id` and `created_at` — instead of being deleted and re-inserted with the wide next-key locks that `REPLACE` takes on both unique indexes; rows whose `is_deleted` flag already holds the target value are no longer rewritten; and child counts are computed with a non-locking read followed by a primary-key-only update instead of a self-joined update. No configuration or database changes are required.

### Deprecated XML configuration

Loading Symfony configuration from XML files is deprecated for Shopware bundles, plugins, and the project-level `config/` directory of an installation, and will stop working with Shopware 6.8, because Symfony 8 removes XML configuration support entirely. This covers service definitions (`Resources/config/services.xml`, `services_test.xml`, `config/services.xml`), route definitions (`Resources/config/routes.xml`, `routes_<env>.xml`, `routes_overwrite.xml`, and any XML file below a `routes/` config directory), and package configuration (`packages/**/*.xml`). Symfony already logs a runtime deprecation for every loaded XML file since Symfony 7.4; Shopware now additionally reports which file — and for bundles and plugins, which bundle — is affected. Shopware-specific XML formats such as `config.xml`, `custom-fields.xml`, or app manifests are not affected.

**Plugin authors:** migrate your `services.xml` to `services.php` using Symfony's `ContainerConfigurator` and your `routes.xml` to `routes.php` using the `RoutingConfigurator`; package configuration can move to YAML or PHP. PHP configuration has been fully supported by the plugin system for years, service ids and wiring stay identical, and both formats can coexist during the transition. YAML definitions remain supported. See the migration example in `UPGRADE-6.8.md`.

### Document templates use the DocumentV2 VAT display condition behind the 6.8 feature flag

The document templates now use the shipping-based `intraCommunityDelivery` condition for displaying VAT information when the `v6.8.0.0` feature flag is active, matching the DocumentV2 document generation path. With the feature flag disabled, the existing billing-address and configured delivery-country behavior remains unchanged. Extensions and themes that render or assert the legacy document templates should test their output with the feature flag enabled before upgrading to Shopware 6.8.

### Line item rule conditions only evaluate product line items

Product specific line item rule conditions (manufacturer, category, tags, properties, dimensions, stock, list price, and similar) now skip non-product goods such as custom product options. Those line items carry no product data, so evaluating them could produce false matches.

### `EntitySearchResult` retains its entity name in v6.8.0

`EntitySearchResult` keeps the `$entity` constructor argument, property, and `getEntity()` method in v6.8.0. Removing the constructor argument would not have provided a forward-compatible migration path: extensions could not construct a result today that also works after the major update. The required call-site changes were therefore disproportionate to the benefit.

The property becomes `readonly`; use the constructor rather than the deprecated `setEntity()` method to provide the entity name. For a non-empty collection, the constructor asserts that the supplied entity name matches the collection's entity name.
### Media path cache busting is configurable

The new `shopware.cdn.path_cache_buster` setting defaults to `true`, preserving timestamped media paths. Set it to `false` to keep paths stable for future media uploads and replacements while retaining `?ts=` query-string cache busting. Configure the CDN to include query strings in its cache key. Existing media paths are not migrated.

### `product-export:generate --force` now regenerates scheduler-managed exports

`--force` promises to ignore the cache and force generation, but for scheduler-managed exports it was a no-op. This aligns the flag with its documented behavior.

### Elasticsearch index updates schedule a reindex when analysis settings change

When updating an Elasticsearch/OpenSearch mapping references an analyzer/normalizer that the live index's analysis settings do not define (for example after an update introduced a new analyzer), `putMapping` fails with `analyzer [...] has not been configured in mappings`. Analysis settings are fixed at index creation and cannot be added to a live index, so this is now handled like the other unrecoverable mapping errors: the affected entity is scheduled for a reindex into a freshly created index, which rebuilds it with the current analysis settings instead of leaving the outdated mapping in place.

### Built-in translation system configurable via `shopware.translation`

The built-in translation system's configuration (previously only editable by decorating `AbstractTranslationConfigLoader`) can now be overridden through the standard Symfony configuration in `config/packages`. Add a `shopware.translation` section to override individual options; any option left unset falls back to the shipped defaults in `translation.yaml`:

```yaml
# config/packages/translation.yaml
shopware:
    translation:
        repository_url: 'https://example.com/translations'
        metadata_url: 'https://example.com/crowdin-metadata.json'
        community_translations_url: 'https://translate.shopware.com'
        documentation_url_snippet_key: 'sw-settings-language.addModal.docsUrl'
        completeness_threshold: 90
        plugins:
            - 'MyPlugin'
        excluded_locales:
            - 'de-DE'
            - 'en-GB'
        pseudo_locales:
            - 'ach-UG'
        plugin_mapping:
            - plugin: 'MyPlugin'
              name: 'MySnippetName'
        languages:
            - name: 'Deutsch'
              locale: 'de-DE'
```

List options (`plugins`, `excluded_locales`, `pseudo_locales`, `plugin_mapping`, `languages`) replace the shipped default entirely rather than merging; provide the full list you want. Setting a list to `[]` clears the shipped default. Decorating `AbstractTranslationConfigLoader` continues to work; a decorator that fully replaces `load()` bypasses these config overrides.

### Product export body media URLs are RFC 3986 encoded

Product export body templates now receive RFC 3986-encoded `MediaEntity::url` and `MediaThumbnailEntity::url` values from their data context. This applies to media URLs such as `product.cover.media.url` and `product.media.*.media.url` in built-in and custom body templates, so feeds such as Google Merchant Center exports can use them without manually encoding their paths.

Other URL-valued strings, including custom fields, are unchanged. Custom body templates that render those values can explicitly encode them with the `sw_encode_url` Twig filter.

### Community translations auto-update can be configured per language

The `translation.update` scheduled task (`UpdateTranslationsTaskHandler`) refreshes the community translations of installed languages. It can now be controlled per language via the new `translationAutoUpdate` flag.

* The `language` entity gains a boolean `translationAutoUpdate` field (API-aware, **enabled by default**). A migration adds the `language.translation_auto_update` column automatically, defaulting to on, so existing languages keep being updated as before.
* Only linked languages are considered: a language flagged for auto-update whose translation is not installed, or whose locale is not part of the translation set (for example built-in or custom languages), is ignored and never triggers a request.
* The flag can be toggled per language on the Administration language detail page ("Snippet updates" card), letting shops opt individual languages out of automatic updates.

### Product migrations no longer fail on MySQL 8.4 with non-standard foreign keys

Migration DDL now retries once with `restrict_fk_on_non_standard_key` relaxed when MySQL 8.4 rejects a statement through MySQL bug [#118151](https://bugs.mysql.com/bug.php?id=118151). The `MigrationStep` DDL helpers do this automatically; raw DDL statements in extension migrations should go through `MigrationStep::executeDdlStatement()`. The method is `@internal` only because it will be removed once MySQL fixes the bug; it is safe to call in the meantime.

### Polyfill packages are installed as declared dependencies

The `shopware/core` and `shopware/platform` package manifests no longer replace Symfony polyfill packages or `paragonie/random_compat`. Composer now installs the polyfills required by the resolved dependency graph instead of treating them as supplied by Shopware. Extension projects that depend on these packages continue to work; their production dependency tree can gain the required polyfill packages. Projects that guarantee the required native PHP functionality can add relevant packages to their own root `replace` section to avoid installing them and reduce their vendor directory size; they must not replace `symfony/polyfill-mbstring`, which Core requires for `mb_ltrim()` on PHP 8.2 and 8.3.

### OpenAPI generation uses swagger-php 6.4

Shopware now requires `zircote/swagger-php` 6.4 for OpenAPI 3.2 generation.
Most extensions are not affected: OpenAPI annotations and attributes continue to be read by swagger-php 6, and extensions that only define `OpenApi\Annotations` or `OpenApi\Attributes` metadata usually do not need code changes.

Extensions or development tools that call swagger-php programmatically should check for removed v4/v5 APIs such as `OpenApi\Generator::scan()` and `OpenApi\Util::finder()`.
The migration is usually straightforward because the instance API `OpenApi\Generator::generate()` is available in swagger-php 4, 5, and 6.
See `UPGRADE-6.7.md` for concrete examples.

### Locale-aware sorting for product property group options

`Shopware\Core\Content\Product\AbstractPropertyGroupSorter::sort()` is deprecated and will be removed with Shopware 6.8. Use the new `sortUsingLocaleCode()` method instead, which sorts property group options using locale-aware (ICU) collation.

### MCP server no longer requires the `MCP_SERVER` feature flag

The MCP server is now always enabled. The `MCP_SERVER` feature flag has been removed, so the `/api/_mcp` and `/store-api/_mcp` endpoints are available without setting any flag. The MCP classes stay marked `@experimental` until 6.8.0, so the API may still change before then.

### MCP entity tools validate association read privileges

The experimental MCP tools `shopware-entity-read`, `shopware-entity-search`, and `shopware-entity-aggregate` now validate the supplied criteria with the same association ACL checks as the Admin API. Loading, filtering, sorting, or aggregating over an association now requires the `<association-entity>:read` privilege in addition to the top-level `<entity>:read` privilege — for example, reading `order` with the `orderCustomer` association requires both `order:read` and `order_customer:read`. Integrations that relied on the missing check must add the association read privileges to their ACL role; requests without such criteria are unaffected.
### MCP tools can be enabled per session through toolsets

The experimental MCP server now advertises only its default meta-tools until a client enables additional toolsets for the current MCP session. Clients can call `shopware-toolsets-list` to inspect available toolsets and `shopware-toolset-enable` to enable one. Enabling a toolset emits `notifications/tools/list_changed` so clients that support MCP list-change notifications can refresh `tools/list`.

Tool execution is still bounded by the configured MCP allowlist. Enabling a toolset only changes which allowlisted tools are advertised for that session.

### MCP clients are notified when app capabilities change

The experimental MCP server now queues `notifications/*/list_changed` messages for active sessions when an app's MCP tools, resources, or prompts change (install, update, activation, deactivation, deletion), so clients can refresh their discovered capabilities.

### MCP tools can be discovered on demand

A fresh MCP session advertises only the three server-owned discovery meta-tools in `tools/list`: `shopware-tool-search`, `shopware-toolsets-list`, and `shopware-toolset-enable`. Every other tool is deferred and reachable in two ways: `shopware-tool-search` returns relevant tool definitions inline for a free-text query, and `shopware-toolset-enable` enables a whole toolset for the session (see the toolset section above). Tool visibility is derived solely from the tool's group — the `discovery` group is the always-advertised surface — so a tool opts into the default surface via `#[McpToolGroup('discovery')]`, not a per-tool flag.

The per-integration MCP allowlist remains the call-time security boundary. `shopware-tool-search` only returns tools that are already allowed for the current integration, and tools outside the allowlist remain uncallable.

### MCP list responses apply allowlists before pagination

MCP `tools/list`, `resources/list`, and `prompts/list` responses now apply the current integration allowlist before protocol pagination is calculated. Clients using `nextCursor` receive full pages of allowed capabilities instead of pages that may be partially or completely empty because hidden capabilities were filtered after paging.

### MCP tools expose a group for operator-facing selection

MCP tool metadata now includes a `group` value in the `/_action/mcp/tools` and `/_action/mcp/capabilities` responses. The Administration MCP allowlist UI and `bin/console debug:mcp` use this value to group tools for operators without changing tool names or call behaviour.

Tools without an explicit group derive one from the longest name prefix they share with another tool at a hyphen boundary, so tools such as `swag-my-plugin-orders` and `swag-my-plugin-products` use `swag-my-plugin` without being combined with tools from another `swag-*` extension. Existing core, plugin, and app tools continue to work.

### SEO URLs for headless sales channels

Headless (API type) sales channels can now generate SEO URLs and be used for product export feeds. Products, categories and landing pages generate SEO URLs for headless channels via dedicated store-api routes (`store-api.product.detail`, `store-api.category.detail`, `store-api.landing-page.detail`).

- The `seo_url_template` entity gained an `is_headless` flag that discriminates the storefront (`frontend.*`) and headless (`store-api.*`) route families. Storefront channels resolve the non-headless templates, headless channels the headless ones — the two inheritance chains are kept separate.
- Three default `store-api.*` templates are seeded (one per entity), mirroring the relative template of their storefront counterpart. Headless channels inherit these defaults just like storefront channels inherit the `frontend.*` defaults; the resolved path is prefixed with the host of the external storefront domain. A per-channel template may be an absolute URL (`https://…`), which is then used as-is.
- The `sales_channel_domain` entity gained an `is_external_storefront` flag (default `false`). For headless sales channels, SEO URLs are only generated for domains flagged as external storefront, and the resolved relative path is prefixed with that domain's URL (one SEO URL per matching domain). The flag can be set per domain in the Administration when editing a headless sales channel's domains.
- Product export feeds now accept both *Storefront* and *Headless* sales channels.

### New BC-change attributes for planned API changes

Shopware previously used `@deprecated tag:vX.Y.Z - reason:*` PHPDoc annotations to document planned backwards-compatibility-affecting changes that are not actual deprecations, such as return type narrowing, new optional parameters, or classes becoming internal or final. In plugin projects these annotations surfaced as `Call to deprecated method` errors in static analysis, although there is no replacement API to migrate to.

Such changes are now documented with dedicated PHP attributes under `Shopware\Core\Framework\Deprecation\BCChange`, for example `#[ReturnTypeNarrowing]`, `#[NewOptionalParameter]`, or `#[BecomesFinal]`. For your project this means:

* Static analysis no longer reports deprecation errors for core methods that merely carry a BC-planning note, so these errors disappear from your pipelines without configuration changes.
* A `@deprecated` annotation on core code is now always an actual deprecation: the functionality will be removed or replaced, and you should migrate as described in the annotation.
* When a core symbol you use carries a BC-change attribute, the attribute tells you whether your project can be affected: attributes implementing `CallSiteCompatibilityChange` concern code *calling* the symbol (for example a parameter type being narrowed, or a parameter you pass as a named argument being renamed), attributes implementing `ExtenderCompatibilityChange` concern classes in your project that *extend or override* the symbol (for example a return type being narrowed or a class becoming final). Each attribute states the version in which the change happens and the new declaration, so you can prepare ahead of the next major.
* If your code does not use the annotated symbol in the affected way, there is nothing to do.

All existing `reason:*` BC-planning annotations in the core have been migrated to these attributes; the remaining `@deprecated` annotations are actual deprecations.

### Product export scheduling decoupled from the cache timestamp

Cron-driven product export generation no longer derives the next run from `generatedAt`, which also anchors the cache validity of the generated feed file. A new `nextGenerationAt` field on the `product_export` entity is set when the first export chunk starts, and the scheduler prefers it over the legacy `generatedAt` + interval calculation. This keeps the schedule anchored to the export start time without making storefront requests treat in-flight exports as stale. The database column is added automatically by a migration; exports generated before the update fall back to the previous `generatedAt`-based scheduling until their next run. No action is required.

### `debug:mcp` lists Store API capabilities

`bin/console debug:mcp` was wired to the Admin MCP server only, so no capability of the Store API MCP server (`/store-api/_mcp`) appeared in its output, and looking one up by name reported "No capability found". Store API tools registered with `shopware.store_api_mcp.tool` were therefore invisible to the standard debugging command.

The command now inspects both MCP servers and groups the output per server, with every section heading naming the server it belongs to (`Store API: Tools (17)`). A new `--scope` option limits it to one of them:

```bash
bin/console debug:mcp                          # both servers
bin/console debug:mcp --scope=store-api        # /store-api/_mcp only
bin/console debug:mcp --scope=api              # /api/_mcp only
bin/console debug:mcp shopware-store-api-context
```

Looking up a capability by name resolves across both servers and the detail view names the owning server. `--integration` still applies to the Admin server only, because integration allowlists are not evaluated for Store API requests; a note points this out when both servers are listed.

### Whole-phrase product-search matches rank higher

Elasticsearch product search now adds an explicit phrase-proximity boost for multi-word searches, weighted above single-word matches. A product whose field contains the full search phrase in order now ranks above one that only contains the individual words scattered around. The same documents still match — this only re-ranks — but `_score` values and borderline orderings shift, which can affect a configured `core.search.minScore`. The per-match-type boosts are configurable via `elasticsearch.search.boost.*`.

### Product export pagination changed to keyset; `getTotal()` deprecated

The product export now paginates products by an `autoIncrement` keyset cursor instead of `LIMIT`/`OFFSET`, removing the `getTotalCount()` timeout on large catalogs.

- `ProductExportResult::getTotal()` and its `$total` constructor argument are deprecated; the export no longer computes a grand total. Use `hasNextBatch()` and `getOffset()` instead.
- The read buffer size is now configurable via `shopware.product_export.read_buffer_size` (default 200). Raise it to reduce per-batch overhead, lower it if a batch hits the worker memory limit.

### `SalesChannelRepositoryIterator` supports autoIncrement keyset pagination

`SalesChannelRepositoryIterator` now seeks by an `autoIncrement` keyset instead of `OFFSET` when the entity has an autoIncrement field and the criteria defines no sorting (mirroring `RepositoryIterator`); a criteria with its own sorting keeps offset iteration. `SalesChannelRepository::getDefinition()` was added for parity with `EntityRepository`.

### Cross-selling by dynamic product group excludes the whole variant family

A cross-selling that uses a dynamic product group no longer returns the product it is displayed on. For variants, the complete variant family is excluded — the currently viewed variant, its parent, and all sibling variants — because variant grouping and main variant resolution would otherwise resolve a sibling back to the viewed product. Previously only the product the cross-selling is assigned to was excluded, which had no effect on variants that inherit their cross-sellings from the parent.

Cross-sellings with a manual product assignment are unchanged. Extensions that need the old result can adjust the criteria in `ProductCrossSellingStreamCriteriaEvent`.
### Changing an SEO URL template regenerates the existing SEO URLs

Writing the `template` field of a `seo_url_template` row now regenerates the SEO URLs of the affected route automatically, instead of leaving them on the old template until the SEO indexer is run manually. The new `SeoUrlTemplateChangeSubscriber` queues `SeoUrlTemplateIndexingMessage` on the `async` transport, and the handler walks the route's entities in batches of 250, chaining one message per batch.

This affects every write path, not just Settings > Shop > SEO:

- Both storefront routes (registered in the `SeoUrlRouteRegistry`) and headless store-api routes (tagged `shopware.entity.seo_url.route`) are covered.
- Writes that do not change the stored `template` value do not queue anything: update commands request a DAL change set, so an idempotent Sync API push of an identical template stays inert. Inserts with an empty or `null` template are skipped as well.
- Extensions and deployment scripts that write `seo_url_template` rows on every install or update will therefore queue a full regeneration pass for the affected route each time. Guard such writes with a value comparison if that is not intended.

### Newsletter route methods keep the `StoreApiResponse` return type

`subscribeWithResponse()`, `confirmWithResponse()` and `unsubscribeWithResponse()` keep
`StoreApiResponse` as their return type in the abstract newsletter routes, in the next major as well.
This withdraws the return type change announced with 6.7.9.0.

A decorator that puts its logic in these methods answers with the response containing the `status`
field, and keeps working on 6.8, where the deprecated `subscribe()`, `confirm()` and `unsubscribe()`
are removed. Those are still required in 6.7 and have to answer with `NoContentResponse`.
### Admin search falls back to the database for entities without an Elasticsearch admin indexer

With Elasticsearch for the Administration enabled, `POST /api/_admin/es-search` silently returned no results for entities that have no admin search indexer, because those entities were dropped from the request. They are now searched over the DAL instead, so an entity registered in the Administration search (`searchTypeService.upsertType()` or a module `defaultSearchConfiguration`) is findable without shipping an indexer. Registering an `AbstractAdminIndexer` for the entity is still the faster option.

## Administration

### Admin Worker loads correctly when the Administration is hosted under a base path

The Vite `asset-path-plugin` now also prefixes the literal `Worker`/`SharedWorker` script URLs that Vite bakes into the Administration bundle with `window.__sw__.assetPath`, the same prefix that is already applied to every other admin asset through the `assetsURL()` helper. Previously these worker URLs were emitted as absolute paths against the domain root (e.g. `new SharedWorker("/bundles/administration/administration/assets/adminWorker-*.js")`) and never passed through `assetsURL()`, so on any Shopware install served from a subdirectory / base path — including Shopware-hosted staging environments mounted under a subpath such as `/staging` — the Admin Worker's `SharedWorker` failed to load (404 against the domain root instead of the base path). This broke admin-worker-driven message-queue and scheduled-task processing in the Administration. Operators running the Administration under a base path now get a working Admin Worker; no action is required.

### Vue Single File Components for Administration extensions

Administration components can now be written as Vue Single File Components. `.vue` files were previously not supported at all: components were registered through the component factory with Twig templates, and there was no way to author or override one as an SFC.

A build-time transform lowers these files onto the Composition-API extension system before Vue compiles them, so an override receives the base component's state instead of replacing its implementation. It runs in every extension build — no configuration needed in your plugin.

Filename decides both identity and role: `sw-my-component.vue` (or `sw-my-component/index.vue`) declares the base component `sw-my-component`, and `sw-my-component.override.vue` overrides it. Two markers declare the extension surface, and both are mandatory in their mode — pass an empty object when there is nothing to declare:

```vue
<!-- sw-my-component.vue -->
<script setup lang="ts">
import { ref } from 'vue';

const count = ref(0);
const internalValue = ref('private');

swDefinePublic({ count });   // `count` is overrideable, `internalValue` is not
</script>
```

```vue
<!-- sw-my-component.override.vue -->
<script setup lang="ts">
import { computed } from 'vue';

const previousState = useSwPreviousState();
const count = computed(() => previousState.count.value * 2);

swDefineOverride({ count });
</script>
```

A few things to know before you start:

* **Every `.vue` file in your own source needs a `<script setup>` block.** A plain `<script>` (Options API) SFC or a template-only SFC is rejected at build time, because the markers that make a component extendable only exist in `<script setup>`. Options-API components continue to work as before through the component factory; this applies to `.vue` files only. SFCs inside `node_modules` are exempt — a dependency's components are not yours to make extendable.
* **An override only works when the base component is itself native-setup.** `sw-my-component.override.vue` extends a base declared with `swDefinePublic()`; it cannot override a component registered through the component factory (Twig / Options API). The base must be authored as a native-setup SFC for `useSwPreviousState()` and the override to resolve.
* **The API is experimental until 6.8.0.** It is marked `@experimental stableVersion:v6.8.0` and may still change.

Rejections surface in your editor as well as in the build: the `valid-shopware-setup` ESLint rule runs the same validation, and `build/vue-setup-transform/templates/custom-plugin-workspace` contains ESLint and TypeScript templates to copy into `custom/` for local plugin development. Full authoring reference: `src/Administration/Resources/app/administration/technical-docs/03-extensibility/07-native-setup-authoring.md`.

### SFC migration codemod now emits native setup components

The `codemod:sfc-migration` developer tool has been rewritten to output the native setup SFC format (`<script setup>` with `swDefinePublic`) instead of the previous `createExtendableSetup()` form, which the build toolchain no longer accepts. The default remains a read-only preview; `--write` creates validated Vue drafts only. Replacing an eligible legacy entry point requires the separate explicit `--replace-originals` option, and Twig templates are retained.

What changed for users of the tool: every generated file must pass the build transform and Vue's compiler before it is written; components that convert only partially receive a `.vue` draft with `TODO(sfc-migration)` comments while their original `index.js` + `.html.twig` stay in place and keep working; components using `mixins` or `Component.extend()` are skipped and reported instead of receiving an Options API `<script>` fallback, which the build now rejects. See `src/Administration/Resources/app/administration/scripts/codemods/sfc-migration/README.md`.

### System config forms show validation errors for the selected sales channel scope

Extension and app configuration forms, and any settings page built on `sw-system-config`, now display server-side validation errors on the field that caused them, for the sales channel selected in the scope switcher. Previously these errors were returned by `POST /api/_action/system-config/batch` but did not reach the field: for sales-channel-specific scopes they were stored under a key that did not match the lookup, the lookup only ever used the initially passed scope, and most field types were never passed the error at all. If your `config.xml` uses `required`, `minLength`, `maxLength`, `min`, `max` or `dataType`, merchants now see why a save was rejected on the scope they have selected. No API changes; the error resolver and error store remain `@private`. (shopware/shopware#18741)
### System config component exposes the selected sales channel scope

The `sw-system-config` component now exposes which sales channel is selected in its scope switcher. The value is seeded from the `salesChannelId` prop and afterwards follows the switcher; later changes to the prop are ignored. It is `null` while the global scope is selected.

The value is available on two surfaces, because they reach different consumers:

* Slot props: the `card-element`, `beforeElements` and `afterElements` slots receive an additional `currentSalesChannelId` prop, and `card-element-last`, which had no slot props, now provides it too. A template override that replaces one of these slots keeps its own copy of the `v-bind` expression and will not see the new prop until it is re-synced against this version.
* Injection: descendants of the component, in particular custom components rendered through a plugin's `config.xml` component elements, can inject the value. Use the defaulted forms below, so your component stays warning-free and crash-free when it renders outside a system config form. With the default in place, a component rendered outside the form reads the same value as the global scope:

```js
// Options API: the injected value is unwrapped, read it directly
inject: {
    swSystemConfigCurrentSalesChannelId: { default: null },
},

// Composition API: you receive the ref itself, read `.value`
const salesChannelId = inject('swSystemConfigCurrentSalesChannelId', ref(null));
```

The provided value is a read-only computed ref. Note that the form body is torn down and rebuilt while the configuration of a not yet visited sales channel loads, so embedded components must not assume instance continuity across a switch.

Existing slot usages keep working unchanged. Previously, following the switcher required traversing `$parent` into private component state or overriding `sw-system-config` itself, both of which break across Administration refactors. (shopware/shopware#18731)
### Conditional visibility for app-registered tabs

`sw.ui.tabs('<position>').addTabItem()` now accepts an optional `visible` boolean, so an app can show or hide its own registered tab depending on the current context (for example the currently opened entity). When omitted, the tab is shown as before, so existing extensions are unaffected.

Re-calling `addTabItem()` for the same `componentSectionId` now updates the existing entry (label and visibility) instead of adding a duplicate, so an app can toggle a tab's visibility for the current context by re-registering it.

```javascript
sw.ui.tabs('sw-order-detail').addTabItem({
    label: 'my-plugin.tabTitle',
    componentSectionId: 'my-plugin-tab',
    visible: order.stateMachineState.technicalName === 'open',
});
```

### Administration caches shared user configuration and lookup data

Administration now reuses a generic cache layer for current-user configuration and frequently loaded lookup data such as the system currency, currencies, taxes, active languages, sales channel types, number range ids, and custom field sets. This reduces repeated Admin API requests when multiple Administration components need the same data.

Current-user configuration is cached per current user through `userConfigService`. Read individual keys from the shared cached `_info/config-me` response and write changes through the same service:

```js
const userConfigService = Shopware.Service('userConfigService');

const response = await userConfigService.search(['my-plugin.config-key']);
const value = response?.data?.['my-plugin.config-key'];

await userConfigService.upsert({
    'my-plugin.config-key': nextValue,
});
```

Shared entity reads can be cached directly on repository reads by passing a stable `cacheKey`:

```js
const criteria = new Shopware.Data.Criteria(1, 500);
criteria.addSorting(Shopware.Data.Criteria.sort('name', 'ASC', false));

const currencies = await Shopware.Service('repositoryFactory')
    .create('currency')
    .search(criteria, Shopware.Context.api, {
        cacheKey: ['shared-data', 'currencies', Shopware.Context.api.languageId ?? 'default'],
        ttl: 5 * 60 * 1000,
    });
```

If your plugin changes cached data and needs a fresh follow-up read, either invalidate the affected cache key prefix or force the next read to reload:

```js
const cacheService = Shopware.Service('cacheService');
const taxRepository = Shopware.Service('repositoryFactory').create('tax');

cacheService.invalidateCaches({
    // Invalidate only the cached tax entries.
    cacheKey: ['shared-data', 'taxes'],
});

const freshTaxes = await taxRepository.search(criteria, Shopware.Context.api, {
    cacheKey: ['shared-data', 'taxes', Shopware.Context.api.languageId ?? 'default'],
    // true bypasses the cached result for this read and stores the fresh response again.
    forceReload: true,
    ttl: 5 * 60 * 1000,
});

cacheService.invalidateCaches({
    // Custom field sets can be invalidated independently from taxes.
    cacheKey: ['custom-field-sets', 'product'],
});
```

### Plugins can use the global Meteor snackbar

Administration plugins can now add and remove snackbars through `Shopware.Service('snackbarService')`. Use `addSnackbar()` with a Meteor snackbar configuration and `removeSnackbar(id)` to dismiss it. Composition API extensions can use the experimental `useSnackbar()` composable, which becomes stable with Shopware 6.8.
### App action buttons in the Media Manager multiselect sidebar

Apps can now surface a custom action button when multiple media are selected in the Media Manager. Registering an action button via the Admin SDK with `entity: 'media'` and `view: 'list'` renders it in the multiselect sidebar's quick-actions list. The button is only shown when **every** selected media item matches the configured `fileTypes` (case-insensitive; omit `fileTypes` to always show it), and the `callback` receives the full list of selected media entities (`{ id, url, fileName, mimeType, fileSize }`). This complements the existing single-item button (`view: 'item'`) and lets apps offer bulk operations — e.g. exporting or converting all selected files — without an extra API round-trip. No changes are required for existing single-item action buttons.
### New media Quick info extension point and selected-item dataset

The media "Quick info" sidebar now exposes an extension point so apps and plugins
can render their own content next to a selected media file. A new
`sw-extension-component-section` with the position identifier
`sw-media-quickinfo-metadata` is rendered directly below the metadata list, and
the currently selected media entity is published as the `sw-media-quickinfo__item`
dataset (`Shopware.ExtensionAPI.publishData`).

Extensions can register a component at the `sw-media-quickinfo-metadata` position
and read the selected item through the data API. App (iframe) extensions must
request the fields they need via `selectors`, for example:

```js
const { data: media } = useDataset('sw-media-quickinfo__item', {
    selectors: ['id', 'fileName', 'fileExtension', 'mimeType'],
});
```

The dataset updates reactively as the user selects a different media file.

## Storefront

### Google reCAPTCHA failures no longer show an error page on non-AJAX forms

A failed Google reCAPTCHA on a non-AJAX form is now rendered as a form error instead of a `403` error page: a missing token asks the customer to retry (new `CaptchaException::RECAPTCHA_TOKEN_REQUIRED_VIOLATION`), other failures show a generic captcha error. Violations without a form field are flashed, field-bound ones keep rendering via `formViolations`. The bot-only honeypot still fails with `403`.

Custom captchas should implement the new `AbstractCaptcha::validate(Request $request, array $captchaConfig): ConstraintViolationList` — an empty list means valid. The deprecated `isValid()`/`getViolations()` are removed in 6.8; until then the default `validate()` delegates to them, so a captcha extending `AbstractCaptcha` keeps working.

One case does change: a captcha extending a shipped captcha (`BasicCaptcha`, `HoneypotCaptcha`, `GoogleReCaptchaV2`, `GoogleReCaptchaV3`) and overriding only `isValid()`/`getViolations()` is no longer consulted, because those implement `validate()` themselves. Migrate it to `validate()` — an unmigrated check stops being applied without an error.

### Theme CLI commands clean up unused theme directories

Each theme compilation writes its CSS/JS into a new seeded directory under `public/theme/<hash>`. Removing the now-unused previous directories was handled exclusively by the daily `theme.delete_files` scheduled task (`Shopware\Storefront\Theme\ScheduledTask\DeleteThemeFilesTask`). In environments where that task does not run reliably — e.g. `bin/console theme:compile` during a build/deploy step, or setups relying on the admin worker without an open Administration session — these directories accumulated without bound and could grow to many gigabytes.

`bin/console theme:compile` and `bin/console theme:change` now run the same cleanup once after compilation, deleting unused theme directories whose files are older than 24 hours (recent directories are kept so cached responses still referencing them keep working). Pass `--no-cleanup` to skip this step and preserve the previous behaviour.

The cleanup logic is now provided by the reusable `Shopware\Storefront\Theme\UnusedThemeDirectoryDeleter` service, which the commands and the scheduled task all use. The scheduled task remains unchanged as a fallback.
### `theme:create` gains `--full` and granular scaffold flags

`bin/console theme:create` accepts new options to scaffold more than the default skeleton: `--with-config` generates `src/Resources/config/config.xml`, `--with-snippets` generates storefront snippet files (`src/Resources/snippet/storefront.{de-DE,en-GB}.json`), and `--with-scss` generates a starter SCSS 7-1 folder structure (`abstracts/`, `base/`, `components/`, `layout/`, `pages/`) referenced from `base.scss`. `--full` is shorthand for all three combined. Default `theme:create` output (without any of these flags) is unchanged. The generated `composer.json` also now sets a real package name (`custom/<theme-name>` instead of a hardcoded placeholder) and pins `shopware/core`.

### `PluginManager.override()` now works for async plugins

Overriding a lazily loaded core storefront plugin no longer silently falls back to the core class. Three defects caused the override to be registered but never applied:

* A core plugin class that finished loading after the override was registered overwrote the override in the registry.
* An element kept the plugin instance it was first initialized with, even after the registered class had changed.
* Re-registering an async plugin with a plain (non-lazy) class left it flagged as async, so it was never initialized at all.

Overriding a plugin that is already initialized on the page now replaces its live instances instead of doing nothing. This replacement happens whenever the class registered under a plugin name differs from the class an existing instance was built with, so it is not limited to `PluginManager.override()` and it can happen during any later initialization pass, for example the one that runs after AJAX-loaded content.

Before an outdated instance is replaced, the PluginManager calls its `destroy()` method and resets its `$emitter`.

**Be aware of the current limitation:** the instance being replaced is the previously registered class, which is usually a core plugin, and most core plugins do not implement `destroy()` yet. Anything such an instance registered outside of itself — most importantly listeners added with `addEventListener` — therefore stays active, and both the replaced and the new instance react to the same event. The PluginManager logs a `console.warn` naming every plugin that is replaced without implementing `destroy()`. Registering your override before the storefront initializes its plugins, which is what a theme entry file does by default, avoids the replacement entirely and is the recommended way to override a plugin.

`PluginBaseClass` now declares a `destroy()` method. Implement it in your own plugins to clean up anything `init()` registered outside the instance:

```js
export default class MyPlugin extends window.PluginBaseClass {
    init() {
        this._onClick = this._onClick.bind(this);
        this.el.addEventListener('click', this._onClick);
    }

    destroy() {
        this.el.removeEventListener('click', this._onClick);
    }
}
```

Note that `PluginManager.override()` still requires the exact selector the plugin was registered with. Overriding `FormCmsHandler` for example only takes effect with the selector `.cms-element-form form`.

### `PluginManager.extend()` can extend plugins under a new name again

`PluginManager.extend(fromName, newName, ...)` threw a `TypeError` for every plugin, because it assigned to the non-writable `prototype` property of the generated class. Extending a lazily loaded plugin additionally failed because the unloaded plugin cannot be used as a base class. Both cases now work; for a lazily loaded parent, the extended class is built once the parent has been loaded.

### Storefront extension bundles use their own webpack chunk loading global

Every storefront extension build inherits the core storefront webpack context and therefore used the default `webpackChunk` chunk loading global, the same one the core storefront and all other extensions use. Sharing that global lets one build's webpack runtime process another build's chunks, so a dynamic `import()` can resolve to a module from a different bundle when chunk ids collide.

Extension builds now set `output.uniqueName` to their technical name, which gives each of them its own global, for example `webpackChunkswag_my_theme`. The core storefront bundle intentionally keeps the default `webpackChunk` global, so its emitted runtime stays unchanged. If you relied on the shared `window.webpackChunk` array, for example to inject chunks into another bundle, use the extension specific global instead. Rebuild your storefront assets to pick up the change.

### The "Top results" sorting label is translatable

`score` is a locked product sorting, so its label could not be edited in Settings > Products > Sorting and only ever existed for `en-GB` and `de-DE`. Every other language fell back to one of those two.

`@Storefront/storefront/component/sorting.html.twig` now renders the `filter.sortByScore` snippet for the `score` sorting instead of its database label, so it can be translated for any language through snippet management or a theme snippet file. All other sortings keep rendering the label configured in the administration.

## App System

### App installation recovers ambiguously failed registrations

A newly registered or rotated app secret only becomes active once the app confirms it. If an installation or secret rotation is interrupted before that confirmation — a crash, a timeout, or an unreachable app server — re-running `bin/console app:install <app-name>` now recovers the app instead of reporting it as already installed.

Recovery also survives an uninstall. An app that adopted a secret the shop never committed rejects everything the shop signs afterwards, including the `app.deleted` webhook, so it keeps its registration across an uninstall and a plain reinstall used to fail with a signature error. The unconfirmed secrets are now kept alongside the committed one when an app is removed, and reinstalling authenticates with them.
### Apps can register custom fields on media folders

Apps can now register custom fields on the `media_folder` and `media_folder_configuration` entities. Previously these entities were not part of the allowed `related-entities` for app custom field sets, so custom fields could only be attached to entities such as `product`, `order`, or `media`.

Relating a custom field set to `media_folder_configuration` is particularly useful because it inherits Shopware's existing folder configuration inheritance: folders that inherit their parent's configuration (`useParentConfiguration`) automatically share these custom field values.

Define the fields in `Resources/config/custom-fields.xml` (the inline `<custom-fields>` element in `manifest.xml` is deprecated since 6.7.13.0):

```xml
<custom-fields xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/System/CustomField/Schema/custom-fields-1.0.xsd">
    <custom-field-set>
        <name>my_app_folder_settings</name>
        <label>My App</label>
        <related-entities>
            <media_folder_configuration/>
        </related-entities>
        <fields>
            <bool name="my_app_enabled">
                <label>Enabled</label>
            </bool>
        </fields>
    </custom-field-set>
</custom-fields>
```

### Media folder settings modal publishes its data sets for app extensions

The administration media folder settings modal (`sw-media-modal-folder-settings`) now publishes its `mediaFolder` and `configuration` entities as data sets. Meteor Admin SDK apps that add a tab or component section to the modal can read and modify them — for example to render folder-level settings and persist them (as custom fields on `media_folder_configuration`) through the modal's native save:

* `sw-media-modal-folder-settings__mediaFolder`
* `sw-media-modal-folder-settings__configuration`

## Hosting & Configuration

### Local translation files and optional automatic updates

The translation system can store downloaded translation files locally instead of on the configured private filesystem. Set `shopware.translation.use_local_filesystem` to `true` and include `var/translation` in the deployed release. Run `translation:download` during the build to populate that directory without creating language or snippet-set records.

The daily translation update task can be disabled with `shopware.translation.scheduled_task.enabled: false`. Use this for immutable deployments that update translation files only during builds. Both options retain their previous behavior by default.

### Optional `Clear-Site-Data` header on customer logout

On customer logout the storefront can send a [`Clear-Site-Data`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Clear-Site-Data) header, so the browser drops data left over from the session. Disabled by default:

```yaml
# config/packages/storefront.yaml
storefront:
    security:
        clear_site_data_on_logout: ['storage']
```

Allowed directives are `cache`, `cookies` and `storage`; anything else is rejected at container build time. Choose them deliberately, as the header applies to the whole origin and not just to the storefront:

* `cookies` covers the registrable domain (eTLD+1), so on a shared domain it also logs the merchant out of the Administration and resets the cookie consent.
* `storage` clears `localStorage`, `sessionStorage`, IndexedDB and service workers, which breaks a PWA on the same origin.
* `cache` makes the browser download all assets again after every logout.

The header requires a trustworthy origin (HTTPS or `http://localhost`) and is ignored by Safari on the logout redirect.

### Fallback thumbnail sizes for remote thumbnails

When remote thumbnails are enabled, operators can optionally configure `shopware.media.remote_thumbnails.fallback_sizes`. It defaults to `[]` and accepts entries with `width` and `height`:

```yaml
# config/packages/shopware.yaml
shopware:
    media:
        remote_thumbnails:
            fallback_sizes:
                - { width: 400, height: 400 }
```

Fallback sizes apply only in remote-thumbnail mode to media in known folders whose configuration has `createThumbnails: true` but no assigned thumbnail sizes. Configured folder-specific sizes remain the normal source when thumbnail creation is enabled. A folder with `createThumbnails: false` is an explicit opt-out and receives no thumbnail URLs, even when fallback sizes are configured. Media without a known folder mapping also receives no fallback thumbnail URLs.

### `SERVICE_REGISTRY_URL` is limited to Shopware domains in production

The service registry decides which Shopware Services a shop installs and where their code is downloaded from. With `APP_ENV=prod`, `SERVICE_REGISTRY_URL` is now only used when its host is `shopware.io` or a subdomain of it. Any other value is ignored and `https://registry.services.shopware.io` is used instead, so a mistyped registry URL no longer breaks service installation on a live shop.

Other environments are unrestricted, so local setups and tests can still point at their own registry.

# 6.7.13.1

## Security Fixes

### Twig templates can no longer call arbitrary PHP functions through `find`, `has some`, and `has every`

The Twig `find` filter and the `has some` / `has every` operators now reject string callables that are not listed in `shopware.twig.allowed_php_functions`, matching the existing behaviour of the `map`, `filter`, `reduce`, and `sort` filters. Templates passing arrow functions (`v => ...`) are unaffected; add any string callable a template legitimately needs to the allowlist.

### Administration password recovery links use a trusted origin

Administration password recovery links are now built from `APP_URL` when no trusted hosts are configured. If trusted hosts are configured, Shopware can continue to use the request host after Symfony has validated it. Ensure that `APP_URL` contains the public HTTP or HTTPS URL of the shop.

### Custom entity and field names are validated before the schema is built

Entity and field names in `Resources/entities.xml` become table and column names in the generated schema. They are now validated when the app or plugin is installed or updated, and may only contain letters, digits, underscores, `$`, and non-ASCII bytes supported by MySQL/MariaDB identifiers. A manifest using whitespace or punctuation such as `-` is rejected with a clear error.

### Store API aggregation names reject control characters

Aggregation names supplied through Store API criteria can no longer contain control characters. Invalid names are rejected before the aggregation query is built. Integrations must use printable names for aggregations.

### ACL roles and protected Administration fields use authorized write paths

Generic Admin API writes can no longer create or update `acl_role` entities. Direct DAL writes to the `admin` fields of users and integrations remain system-only; the authenticated Administration API controllers continue to authorize these changes, while self-profile and integration management work as before.

The Administration service method `Shopware.Service('integrationService').updateAdmin()` is deprecated and will be removed in Shopware 6.8. Use the integration repository instead:

```javascript
const integrationRepository = Shopware.Service('repositoryFactory').create('integration');
await integrationRepository.save(integration);
```

### Nested `productReviews` associations follow the same visibility rules as the top-level association

Store API criteria that load product reviews through a nested association now apply the same review visibility rules as the top-level `productReviews` association: approved reviews, plus the pending reviews of the logged-in customer. Previously those rules were applied to the top-level association only. Integrations that read reviews through a nested association can receive fewer reviews than before.

### Oversized sales channel criteria are rejected

`SalesChannelRepository` applies the restrictions of the sales channel definitions — the sales channel scope and entity-specific filters such as product availability — to the first 99 criteria nodes it walks. Criteria with more nested associations than that kept the remaining nodes unrestricted. Such criteria are now rejected with a `400` and the error code `SYSTEM__CRITERIA_TOO_MANY_NESTED_CRITERIA` instead of being answered with partially restricted data. No storefront request produces criteria of that size; integrations that build them must split them into several requests.

### Media file extensions are validated on every write

Direct writes to `media.fileExtension` now use the same configured extension allowlist as media uploads. Invalid public or private media extensions are rejected with the error code `MEDIA_ILLEGAL_FILE_EXTENSION`.

### Media import URL checks apply to the address that is connected to

Media imports send the request to the address the URL check resolved, and check every resolved address instead of only the first IPv4 one. A `FileUrlValidatorInterface` implementation can still reject a URL, but can no longer allow a private or reserved address. To import media from a host in such a range, set `shopware.media.enable_url_validation` to `false`.

### Webhook target validation hardened

Webhook delivery now validates outbound targets before every request and before every followed redirect. By default, webhook targets must use HTTPS and resolve only to public IP addresses. HTTP endpoints, IP-literal targets, and internal network targets are rejected unless the operator explicitly allows the required traffic through `shopware.app_system.allow_unencrypted_traffic` or `shopware.app_system.allowed_private_ip_addresses` in `shopware.yaml`.

Shopware pins the DNS result used during validation to the actual webhook HTTP request, reducing DNS rebinding risk between validation and connection.

### Guest document downloads are rate limited

Guest document download requests using a deep link code are now covered by the guest login rate limiter. Repeated invalid authentication attempts are rejected once the configured limit is reached; a successful authentication resets the limit.

## Critical Fixes

### Elasticsearch index updates schedule a reindex when analysis settings change

When an Elasticsearch/OpenSearch mapping update references an analyzer or normalizer that the live index's analysis settings do not define, updating the mapping fails. Analysis settings cannot be added to a live index, so the affected entity is now scheduled for a reindex into a freshly created index with the current analysis settings instead of leaving the outdated mapping in place.

### Document rendering supports decorated Twig environments

The document renderer now type-hints the base `Twig\Environment` instead of Shopware's `TwigEnvironment`, so a decorated `twig` service no longer breaks document generation. The sales channel business timezone override applies only when Shopware's `TwigEnvironment` is in use. With a decorator that does not extend it, documents render in Twig's default timezone.

# 6.7.13.0

## Critical Fixes

### Store API requests no longer start PHP sessions

Store API requests now remain stateless unless application or extension code explicitly starts a session. Previously, several sales channel and Storefront event subscribers could initialize Symfony's lazy session factory during Store API requests, causing unnecessary session storage growth and potentially taking PHP session locks. Storefront session handling, including customer imitation, remains unchanged.

## Core

### Admin Elasticsearch listings fall back to the database on deep pagination

Admin Elasticsearch searches (`ENABLE_OPENSEARCH_FOR_ADMIN_API`) now fall back to the database searcher when a request's `offset + limit` exceeds the configured admin index `max_result_window`, instead of sending a request that OpenSearch rejects with `Result window is too large`. This previously broke listings such as the customer grid when jumping to a deep or last page.

### Product `descriptionTeaser` backfill runs once as a post-update indexer

The `product.description_teaser.indexer` that fills `descriptionTeaser` for products predating the column (introduced in 6.7.12) is now a one-time post-update indexer: it runs once through the post-update flow after the update and is no longer executed by `bin/console dal:refresh:index`. It rebuilds each teaser from the current description and rewrites only the rows whose stored value is missing or out of date. Ongoing changes continue to be kept in sync synchronously on write by the product description-teaser subscriber.

### Agentic file names are matched case-insensitively

Agentic file names are now matched case-insensitively. Core uses the standard `/AGENTS.md` spelling, while existing `/agents.md` URLs, lowercase extension templates, enabled states, and merchant overrides continue to work.

### Shopware Services are updated by the scheduled service task

The daily `services.install` scheduled task now runs a reconcile pass: in addition to installing newly-registered Shopware Services, it converges every already-installed service to the latest revision advertised by the service registry. Previously an installed service was only updated when it pushed an update via `POST /api/services/trigger-update`, so a shop that missed a push stayed on a stale revision until the next push. Updates are idempotent — a service already on the latest revision is a no-op — and no configuration change is required (services must be enabled as before).

### Per-thumbnail post-processing event and progressive JPEG thumbnails

Thumbnail generation gained a new extension point and two output improvements:

- A new event `Shopware\Core\Content\Media\Event\ThumbnailGeneratedEvent` is dispatched after each individual thumbnail is written to disk. Until now there was no hook for post-processing a single thumbnail — `MediaPathChangedEvent` only fires once for the whole batch — so plugins that want to optimise thumbnails (e.g. `jpegoptim`, `pngquant`) had nowhere to attach. The event exposes the `mediaId`, `thumbnailId`, thumbnail `path`, `mimeType` and the `FilesystemOperator` the thumbnail was written to, so a subscriber can read the file back, optimise it and write it again.
- JPEG thumbnails are now written as progressive (interlaced) JPEGs. Progressive JPEGs show a full low-quality preview immediately and sharpen as they load, improving perceived load time (LCP) on slow connections; file size stays equal or slightly smaller. This is transparent — no action required.
- Batch thumbnail generation is now resilient: a single unprocessable media (corrupt source, unsupported format, filesystem error or a throwing `ThumbnailGeneratedEvent` subscriber) is logged and skipped, and its partially written files are cleaned up, so the remaining media in the batch still get their thumbnails instead of the whole run aborting. The single-media path (`Shopware\Core\Content\Media\Thumbnail\ThumbnailService::updateThumbnails()`) still surfaces the exception to its caller. (shopware/shopware#18250)

### Installed translations are refreshed automatically by a scheduled task

A new `translation.update` scheduled task now keeps installed translations up to date without manual intervention. It runs once a day by default and does the same work as the `translation:update` console command / `POST /api/_action/translation/update` route: it fetches the latest remote metadata and re-downloads every installed locale whose translation changed. Shops without any installed translation are a no-op and make no remote request.

Operators can change the interval like any other scheduled task (`scheduled_task.run_interval`) or disable it entirely with `bin/console scheduled-task:deactivate translation.update`.

The translation update orchestration was extracted into the new internal service `Shopware\Core\System\Snippet\Service\TranslationUpdater`, which the Admin API route and the scheduled task share. The HTTP contract of `POST /api/_action/translation/update` is unchanged, except that it now short-circuits without a remote request when no translation is installed (the response is identical).

### Cloning an entity no longer fails on the write-protected `wasModifiedByUser` field

Cloning any entity that carries a `wasModifiedByUser` field previously always failed with `FRAMEWORK__WRITE_CONSTRAINT_VIOLATION` on `wasModifiedByUser`, because the clone copied that write-protected field's value into the insert payload. In the Core this affected mail templates (e.g. via `POST /api/_action/clone/mail-template/{id}`), and it applies equally to any extension entity using the field. The clone process now omits the field, so the cloned entity is correctly created as a fresh, non-user-modified record. (shopware/shopware#18233)

### Standard integrations now honor `sw-app-user-id`

Admin API requests authenticated with a standard integration access key now support the `sw-app-user-id` header the same way app integrations already do. When the header contains a valid user id, the resolved permissions are restricted to the intersection of the integration ACL privileges and the user's ACL privileges. Invalid or empty `sw-app-user-id` values continue to be ignored.

### Cache invalidated on cross-selling updates and deletions

Editing or deleting a product cross-selling entry, including assigned products and translations, now correctly invalidates the product detail route cache and prevents stale storefront results.

### Enforce "Allow payment change after checkout" when re-paying an order

`Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute` now rejects payment methods whose `afterOrderEnabled` ("Allow payment change after checkout") flag is disabled, matching the methods offered on the edit-order page. Previously the flag was only applied as a UI filter, so a payment method that renders its own JavaScript payment button (e.g. PayPal smart buttons) could still be used to pay an existing order. The store-api route `POST /store-api/order/payment` now returns `CHECKOUT__ORDER_PAYMENT_METHOD_NOT_CHANGEABLE` (HTTP 403) for such methods. (shopware/shopware#17495)

### ZUGFeRD correction documents derive shipping handling from document metadata

For cancellation and other correction-style ZUGFeRD documents, delivery amounts are now serialized according to their business meaning:
- refunded shipping is emitted as an allowance
- charged return shipping is emitted as a charge
- zero-value shipping is omitted from the XML entirely

Plugins that build `Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument` instances manually should set document metadata via `withDocumentInformation()` before adding deliveries when they expect correction-specific shipping output. The delivery serialization now derives from the document type that was set there.

### Text-based media is stored and served with an explicit charset

Text-based media files (`text/plain`, `text/csv`, `text/html`, `text/xml`, `application/json`, `application/xml`) are now written to storage with an explicit `Content-Type: …; charset=utf-8`. Previously the charset was missing, so serving such a file directly from object storage / CDN made browsers fall back to a non-UTF-8 encoding and render umlauts and other multi-byte characters as mojibake. This applies to both the server-side upload path and the presigned direct-to-S3 upload path. The `mimeType` persisted on the media entity stays bare (without the charset parameter), so no code reading it needs to change.

### Webhooks are signed with the current app secret after a secret rotation

Webhook deliveries now resolve the app's HMAC signing secret at delivery time instead of reusing the secret captured when the webhook was queued. A webhook that was queued or retried across an app-secret rotation was previously still signed with the stale secret, so the receiving app rejected it with a signature error until the message was dropped. Apps no longer need to do anything — deliveries that span a rotation are signed with the secret the app currently verifies against.

### SVG validator accepts more passive extension assets

SVG media validation now accepts additional passive SVG elements, attributes, metadata, inline fonts, safe animation attributes, known editor namespaces, public SVG doctypes without internal subsets, and embedded raster image data URIs. This allows more SVG assets shipped by extensions and themes to pass validation while still rejecting active content such as external references, processing instructions outside scoped metadata, `foreignObject`, and entity definitions.

### DAL validation now checks for non-standard foreign keys (MySQL 8.4)

`dal:validate` detects foreign keys that reference something other than a complete PRIMARY or UNIQUE key of the target table.
MySQL 8.4 rejects such FKs when `restrict_fk_on_non_standard_key=ON`, which breaks schema imports.

**Plugin authors:** if `dal:validate` newly fails for your plugin, the fix is to extend the FK to cover all columns of the referenced key (typically adding the missing `version_id` column).
If you need to temporarily suppress a specific constraint name while migrating, pass `--tolerate-foreign-key=<constraint_name>` to the command.

### Plugin activation rolls back when post-activation fails

Plugin activation now restores the plugin's `active` flag when a post-activation subscriber fails. Previously, a failure after the active flag was persisted, for example during storefront theme refresh, could leave the plugin marked active even though activation failed.

### Deprecated `maintenanceIpWhitelist` wording of the sales channel

The non-inclusive `maintenanceIpWhitelist` wording on the sales channel is deprecated in favor of `maintenanceIpAllowlist`.
The deprecated members keep working and will be replaced in Shopware 6.8. Migrate your code now:

- DAL: use the new field `maintenanceIpAllowlist` instead of `maintenanceIpWhitelist`. Both fields are available and kept in sync during the transition.
- `Shopware\Core\System\SalesChannel\SalesChannelEntity`: use `getMaintenanceIpAllowlist()` / `setMaintenanceIpAllowlist()` instead of `getMaintenanceIpWhitelist()` / `setMaintenanceIpWhitelist()`.
- `Shopware\Core\SalesChannelRequest`: use the constant `ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_ALLOWLIST` instead of `ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST`.
- `Shopware\Core\Framework\Adapter\Kernel\HttpCacheKernel`: use the constant `MAINTENANCE_ALLOWLIST_HEADER` instead of `MAINTENANCE_WHITELIST_HEADER`.

The new `sales_channel.maintenance_ip_allowlist` database column is added and kept in sync with the deprecated `maintenance_ip_whitelist` column. The deprecated field and column will be removed with Shopware 6.8.

### Deprecated `BeforeCacheControlEvent` and the administration cache-control marker

`Shopware\Core\Framework\Adapter\Cache\Http\Event\BeforeCacheControlEvent`, `Shopware\Administration\Controller\AdministrationController::CACHE_ID_HEADER` and `Shopware\Administration\Controller\AdministrationController::CACHE_ID_ADMINISTRATION` are deprecated and will be removed in Shopware 6.8.0.0, together with the internal dispatching and consuming code.

The event and related headers only existed to skip the `CacheControlListener`. With the new caching (the `CACHE_REWORK` feature flag, which becomes the default in 6.8.0) the response `Cache-Control` headers will be returned to the calling client and whole construction will be removed.

### Deprecated core script response rendering

`Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacade::render()` is deprecated and will be removed in Shopware 6.8.0.0.
The method remains available in 6.7 for backwards compatibility when the Storefront bundle and a `SalesChannelContext` are available.

Extension authors should type the script `response` service for the hook they implement:
use `Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacade` for admin-api and store-api hooks and avoid `render()` there;
use `Shopware\Storefront\Framework\Script\Api\StorefrontScriptResponseFactoryFacade` for Storefront hooks that render Twig templates.

```twig
{# admin-api and store-api hooks #}
{# @var services.response \Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacade #}

{# Storefront hooks #}
{# @var services.response \Shopware\Storefront\Framework\Script\Api\StorefrontScriptResponseFactoryFacade #}
```

### Deprecated unused Composer dependencies

The following Composer dependencies are deprecated as Shopware dependencies and will be removed with the next major version:

- `doctrine/inflector`
- `symfony/monolog-bridge`
- `symfony/proxy-manager-bridge`

If your extension uses classes from one of these packages, declare the package explicitly in your extension's `composer.json`.
Generally, do not rely on Shopware dependencies being installed transitively.

### Declarative custom fields via `Resources/config/custom-fields.xml`

Plugins and apps can now define custom fields declaratively in a `Resources/config/custom-fields.xml` file.
Shopware automatically handles creation, updates, and removal during the extension lifecycle (install, update, uninstall).

This eliminates the boilerplate `CustomFieldsInstaller` service and plugin lifecycle hooks that were previously required for plugins.
For apps, the same file-based approach replaces the inline `<custom-fields>` section in `manifest.xml` (now deprecated).

The XML format is the same one already used by apps in the manifest:

```xml
<?xml version="1.0" encoding="utf-8"?>
<custom-fields xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/System/CustomField/Schema/custom-fields-1.0.xsd">
    <custom-field-set>
        <name>my_plugin_fields</name>
        <label>My Fields</label>
        <label lang="de-DE">Meine Felder</label>
        <related-entities>
            <product/>
        </related-entities>
        <fields>
            <int name="my_plugin_weight">
                <label>Weight</label>
                <position>1</position>
            </int>
        </fields>
    </custom-field-set>
</custom-fields>
```

New classes:
- `Shopware\Core\System\CustomField\CustomFieldSetPersister` — shared persistence logic for custom field sets
- `Shopware\Core\System\CustomField\CustomFieldXmlLoader` — loads and validates `custom-fields.xml` files

The custom field XML DTO classes have been moved from `Shopware\Core\Framework\App\Manifest\Xml\CustomField` to `Shopware\Core\System\CustomField\Xml` to make them properly reusable outside the app system.

### Custom fields can be marked searchable declaratively

Apps and plugins can now flag a custom field as searchable directly in XML via a new `<include-in-search>` element, setting the field's `includeInSearch` property on creation. For apps, declare it inside the `<custom-fields>` section of `manifest.xml`:

```xml
<custom-fields>
    <custom-field-set>
        <name>swag_example_set</name>
        <label>Example</label>
        <related-entities>
            <product/>
        </related-entities>
        <fields>
            <text name="swag_example_field">
                <label>Example field</label>
                <include-in-search>true</include-in-search>
            </text>
        </fields>
    </custom-field-set>
</custom-fields>
```

The same element also works in the file-based `Resources/config/custom-fields.xml` format used by plugins and apps.

Previously `includeInSearch` defaulted to `false` and could only be toggled through the Admin UI or the Admin API — and for app-owned custom fields that was not possible at all, because their field sets are not editable in the Administration. A searchable custom field is picked up by the product search indexing and becomes selectable for ranking configuration under Settings → Search. The element defaults to `false`, so existing extensions are unaffected.

### Scheduled task execution moved to `ScheduledTaskExecutor`

The orchestration logic of `ScheduledTaskHandler::__invoke()` (loading the task, marking it running or failed, and rescheduling it) has moved into the new `ScheduledTaskExecutor` service.
The executor is injected into every scheduled task handler tagged as `messenger.message_handler` via the new `ScheduledTaskExecutorCompilerPass`.
Scheduled task handlers registered through the container — the standard way plugins register them — require **no changes** and keep working as before.

The inline execution logic in `ScheduledTaskHandler::__invoke()` is deprecated and will be removed in Shopware 6.8.0.0.
This only affects code that instantiates a `ScheduledTaskHandler` manually instead of resolving it from the container (for example in tests).
In that case, set the executor explicitly to opt into the new behaviour, otherwise the handler falls back to the deprecated inline logic and triggers a deprecation warning:

```php
$handler = new MyScheduledTaskHandler($scheduledTaskRepository, $logger);
$handler->setScheduledTaskExecutor(new ScheduledTaskExecutor($scheduledTaskRepository, $logger, $clock));
$handler($task);
```

The protected `markTaskRunning()`, `markTaskFailed()`, and `rescheduleTask()` hooks are deprecated and will be removed in Shopware 6.8.0.0. They remain overridable until then — the executor still routes through an overridden `rescheduleTask()` for backwards compatibility — but new code should not rely on them, as the executor owns the status transitions and rescheduling.

If you need to control when a task runs next (instead of the default `now + runInterval` schedule), implement the new `DynamicallyScheduledTaskHandler` interface rather than overriding `rescheduleTask()`. The executor asks the handler for the next execution time via `getNextExecutionTime()` and persists it, so the handler only answers the "when", not the "how":

```php
use Shopware\Core\Framework\MessageQueue\ScheduledTask\DynamicallyScheduledTaskHandler;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;

class MyScheduledTaskHandler extends ScheduledTaskHandler implements DynamicallyScheduledTaskHandler
{
    public function getNextExecutionTime(ScheduledTask $task, ScheduledTaskEntity $taskEntity): ?\DateTimeInterface
    {
        // return the next execution time, or null to fall back to the default `now + runInterval` schedule
        return $this->nextPendingRecordTimestamp();
    }
}
```

### Sales Channel business timezone

Sales Channels now have an optional business timezone setting. When configured, document rendering for that Sales Channel uses this timezone instead of Twig's default timezone.

Without a value, document rendering keeps its previous behaviour, which depends on the entry point: documents generated during a Storefront request can pick up the customer's browser timezone, while documents generated from the Administration or the message queue use Twig's configured default timezone. Starting with Shopware 6.8, this entry-point dependency is removed: without a business timezone, documents always render in Twig's configured default timezone (UTC unless changed via the `twig.date.timezone` configuration), regardless of how the document is generated.

### `EntitySearchResult` and result subclasses deprecated

`EntitySearchResult`, `ProductListingResult`, and `ProductReviewResult` are deprecated for v6.8.0.
In v6.8.0 `EntitySearchResult` will no longer extend `EntityCollection`, and the two subclasses will no longer extend `EntitySearchResult`. The classes remain `Struct`, so extensions, states, and JSON serialization keep working.

To prepare, for all three classes:

- Call collection methods (`first`, `last`, `filter`, `getElements`, `slice`, …) on `$result->getEntities()` instead of directly on the result.
- In Twig, use `{% for x in searchResult.entities %}` instead of `{% for x in searchResult %}`, and `searchResult.entities` instead of `searchResult.elements`.
- Stop relying on `instanceof EntityCollection` for any result, or on `instanceof EntitySearchResult` for a `ProductListingResult` / `ProductReviewResult`. Parameter and return types declared as those will reject results in v6.8.0.

For `EntitySearchResult`:

- The wrapper becomes immutable: `$total`, `$entities`, `$page`, `$limit`, `$criteria`, `$context`, and `$aggregations` become `readonly`, and the setters (`setPage()`, `setLimit()`, `setEntity()`, `setCustomFields()`) will be removed.
- Stop using `getEntity()` / `setEntity()` and the `$entity` field. The entity name is no longer exposed by the result wrapper in v6.8.0.
- Code that constructs a result directly (`new EntitySearchResult(...)`) must be updated for the v6.8.0 constructor: the `$entity` parameter is removed and the remaining parameters reorder.

For `ProductListingResult`:

- Build it with the new `ProductListingResult::fromSearchResult(...)` factory instead of `createFrom` + setters. The factory signature is stable across the v6.8.0 cut.
- The listing state (`$sorting`, `$currentFilters`, `$availableSortings`, `$streamId`, `$page`, `$limit`) stays mutable: listing processors modify the result after construction by design, so `addCurrentFilter()`, `setSorting()`, `setAvailableSortings()`, `setStreamId()`, `setPage()`, and `setLimit()` remain supported. Only the surface inherited from `EntitySearchResult` goes away.

For `ProductReviewResult`:

- Build it with the new `ProductReviewResult::fromSearchResult(...)` factory instead of `createFrom` + setters.
- The class becomes fully immutable: `$matrix`, `$productId`, `$customerReview`, `$totalReviewsInCurrentLanguage`, and `$parentId` become `readonly`, and the setters (`setMatrix()`, `setProductId()`, `setCustomerReview()`, `setTotalReviewsInCurrentLanguage()`, `setParentId()`) will be removed — pass the values to `fromSearchResult()` instead.

### Faster category creation and editing

Creating or editing a single category no longer re-indexes unrelated categories. Previously, adding a sub-category or changing a single field (such as the name) of one category re-indexed the whole branch — every sibling and the parent's entire subtree — which produced a large number of SQL queries and noticeably slow saves in shops with many categories. A category write now only re-indexes the affected category and its own descendants (plus the parent's child count when a category is created, deleted, or moved to a different parent). Merchants with large category trees will see significantly faster saving in the Categories module and lower database load.

For extension developers: as a consequence, the `CategoryIndexerEvent` is now dispatched with a smaller id set for these writes. If you subscribe to it and previously relied on receiving sibling categories that were not actually affected by the write, adjust your listener to resolve the categories it needs explicitly.

### Rule Builder: "all / at least one" toggle is now config-driven

Whether a line item condition offers the "all / at least one" match-all toggle is now decided by the condition's `getConfig()` (`isMatchAny`) instead of being shown for every line item condition.

### Rule Builder: line item purchase price uses a net/gross type field

`LineItemPurchasePriceRule` (`cartLineItemPurchasePrice`) now stores the price type as a `type` field (`gross` / `net`) instead of an `isNet` boolean, aligning it with the generic rule configuration and rendering it via `sw-condition-generic`.

### Not-null translation columns accept falsy defaults

`DefinitionValidator` no longer reports a not-null translation column as missing a default when the column has a falsy but set default such as `0`, `'0'`, or `''`. Only a `null` default is now treated as missing. This removes false positives for plugin entity definitions that use such defaults.

### OneToMany association limit now respects sort order across joined tables

When a paginated OneToMany association was loaded with both `setLimit()` and a sort on a field belonging to a joined entity (i.e. `product.media.position`), the limit could select the wrong rows.

No changes to calling code are required, but the sorting of associations with a limit may change for OneToMany associations, as they now reliably return the top-N rows in the requested order.

### Added `--no-scaffold` flag to `plugin:create` command

The `bin/console plugin:create` command now accepts a `--no-scaffold` flag that skips all optional scaffold generators, producing only the minimal required plugin skeleton.

```bash
bin/console plugin:create MyPlugin MyNamespace --no-scaffold
```

### Dynamic product groups can keep matching variants ungrouped

Now, product streams have a new boolean field `displayAsGroup` and a corresponding Administration toggle "Keep matching variants grouped" on the dynamic product group detail page.
When `displayAsGroup` is disabled, matching variants are returned and rendered individually instead of being grouped or remapped.

The new database field `product_stream.display_as_group` defaults to `1`, so existing product streams keep the previous grouped behavior after migration unless they are changed explicitly.
Also, `ProductStreamBuilderInterface` and `buildFilters()` are deprecated and will be removed in `v6.8.0.0`; use the new `AbstractProductStreamBuilder::enrichCriteria()` as the primary extension point instead.

### New `Criteria::excludeFields()` for reduced entity reads

`Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::excludeFields()` is a denylist counterpart to `addFields()`: it loads the full, typed entity (and its associations) but omits the named storage columns from the read — useful to skip heavy, off-page columns (for example a large `product.description`) without loading them for every row.

Unlike `addFields()`, which is an allowlist that returns `PartialEntity` instances and drops everything not listed, `excludeFields()` returns the regular entity type (e.g. `ProductEntity`) with the excluded properties left at their default value. Typed getters, `instanceof` checks and `*.loaded` subscribers therefore keep working. It cannot be combined with `addFields()` on the same criteria, and required or write-protected top-level fields cannot be excluded — attempting to exclude one (e.g. `stock`) or an unknown field throws a `DataAbstractionLayerException`.

Product listings now use this instead of the previous field allowlist: when reduced listing loading (`core.listing.partialDataLoading`) is enabled, listings load full product entities minus `description`, `keywords` and `customSearchKeywords`, so `customFields`, associations and other data remain available. As a result the `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader::PARTIAL_LISTING_FIELDS` constant is deprecated and will be removed in `v6.8.0.0`.

### Mail template simulation supports form data

Mail template simulation now provides sample data for the `contactFormData`, `reviewFormData` and `revocationRequestFormData` variables, so simulating these form templates no longer fails.

Extensions that add their own forms can supply sample data for their form variables too. Declare the variable as `Shopware\Core\Framework\Event\EventData\FormDataObjectType` (instead of a schemaless `ObjectType`) in the event's `getAvailableData()`, and provide the data via the new `Shopware\Core\Content\MailTemplate\Service\Event\MailDataSimulatorFormDataEvent`:

```php
public function provideFormData(MailDataSimulatorFormDataEvent $event): void
{
    if ($event->flowEventName === 'my_custom_form.send' && $event->variableName === 'myCustomFormData') {
        $event->setData(['field' => 'value']);
    }
}
```

### Deprecated `UnmappedFieldException` in the DBAL sub-namespace

`Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException` is deprecated in favor of the new `Shopware\Core\Framework\DataAbstractionLayer\Exception\UnmappedFieldException`. The deprecated class keeps working and will be removed in Shopware 6.8.

`DataAbstractionLayerException::unmappedField()` returns the deprecated class while the `v6.8.0.0` flag is off and the new class once it is active. Prepare your code now:

- Switch your `use` and `catch` statements to the new `Shopware\Core\Framework\DataAbstractionLayer\Exception\UnmappedFieldException`.
- While you still support the deprecated version, catch both classes, since they do not share a common parent.

## Storefront

### Deprecated `type` variable in address manager templates

The Twig variable `type` in the address manager modal templates (`address-manager-modal-list.html.twig`, `address-manager-modal-create-address.html.twig`, and `address-manager-item.html.twig`) is deprecated in favor of `addressType`.
The old variable remains available during the transition and will be removed with Shopware 6.8.
Themes and plugins that extend these templates should migrate to `addressType`.

### Form validation messages use Storefront snippets

Validation errors rendered by the Storefront `FormController` for contact, newsletter, and revocation forms are now translated from the violation code through Shopware's snippet system. This ensures that the active Storefront language is used instead of Symfony's validator translation catalogue. Plugin authors using custom constraints in these forms should provide matching `error.<violation-code>` entries in `Resources/snippet/storefront.<locale>.json`.

### Link categories get SEO URLs again and redirect to their target

Categories of type "link" are no longer excluded from SEO URL generation in `NavigationPageSeoUrlRoute`. Since link categories redirect to their configured target when opened, their own SEO URL (e.g. from an old bookmark, an external link, or a category that was switched from type "page" to "link") now resolves to that redirect instead of a 404. Categories of type "folder" remain excluded, and link categories are still omitted from the sitemap. Existing shops get the URLs (re)generated with the next category indexing, e.g. via `bin/console dal:refresh:index`.

### robots.txt allows crawling product feed tracking URLs

The default storefront `robots.txt` now emits `Allow: /*referringSalesChannel=` alongside the existing `Disallow: /*?`. Product feed links (the sales-channel tracking feed used by agentic commerce) carry a `referringSalesChannel` query parameter; the blanket `Disallow: /*?` previously stopped Googlebot from crawling those landing pages, which caused Google Merchant Center to disapprove the products. The clean, parameter-free URL is still what gets indexed via the page's `rel=canonical`. Plugins that emit their own tracking parameters can add an equivalent `Allow` directive by subscribing to `RobotsPageLoadedEvent`.

### Paginated storefront URLs now have unique canonical URLs

Storefront listing pages now include their page number in the canonical URL when pagination is used. This ensures that each paginated page has its own canonical URL, allowing search engines to index the pages in the sequence correctly.

### Deprecated `AbstractDomainLoader::load()` in favor of `loadDomains()`

`Shopware\Storefront\Framework\Routing\AbstractDomainLoader::load()` is deprecated and will be removed with Shopware 6.8. Use the new `loadDomains()` method instead, which returns a `Shopware\Storefront\Framework\Routing\Struct\DomainCollection` of `Shopware\Storefront\Framework\Routing\Struct\DomainStruct` objects, keyed by domain URL.

`loadDomains()` is already available: its default implementation builds the collection from `load()` for backward compatibility, but will become abstract with 6.8. If you decorate `AbstractDomainLoader`, implement `loadDomains()` in your decorator. If you consume the result, look up entries via the collection (e.g. `$domains->get($url)`) and access the values as objects (e.g. `$domain->url`) instead of array keys (`$domains[$url]['url']`).

### FormFieldToggle can toggle related submit button labels

The storefront `FormFieldToggle` plugin now supports optionally updating a related button label when the toggle value changes.

This is useful for dynamic forms (for example subscribe vs unsubscribe flows) where hidden/visible field groups and the submit action label should stay in sync without introducing a dedicated custom plugin.

For extension and theme developers, two optional data attributes are available on the controlling field:

- `data-form-field-toggle-button-target`: CSS selector for the related button.
- `data-form-field-toggle-button-text`: Alternate button text that is applied when the toggle target is hidden.

If those attributes are not provided, `FormFieldToggle` behaves exactly as before.

## API

### Store API OpenAPI: JSON schema files take precedence over generated entity schemas

The `StoreApiGenerator` now checks whether a component schema already exists in the JSON schema files before using the OpenAPI schema generated from the PHP `EntityDefinition`. If a match is found, the PHP-generated OpenAPI component is ignored.

JSON schema files are now the sole source of truth for any entity they define. Properties, required fields, and other schema details from the PHP `EntityDefinition` will not be merged into the JSON schema.

If you maintain a bundle that provides both a PHP `EntityDefinition` and a JSON schema file under `Resources/Schema/StoreApi/components/schemas/` for the same entity, ensure the JSON file is complete. The PHP `EntityDefinition` remains responsible for DAL and internal entity handling.

All core Store API entity components are now JSON-owned. The unused PHP-generated `LanguageJsonApi`, `MainCategoryJsonApi`, `NewsletterRecipientJsonApi`, `PaymentMethodJsonApi`, `SalutationJsonApi`, and `ShippingMethodJsonApi` compatibility components are no longer emitted; use their flat component counterparts instead.

See the [JSON as the Source of Truth for API Schema](https://github.com/shopware/shopware/discussions/15100) RFC for the full rationale and roadmap.

### Purchase prices removed from Store API order line item payloads

Order line item JSON serialization no longer exposes product `purchasePrices` in the payload returned by Store API order responses.
Purchase prices are confidential cost data and were not intended to be part of customer-facing APIs.
Headless storefronts, apps, and integrations must stop reading `orders.elements[].lineItems[].payload.purchasePrices`; there is no Store API replacement for this confidential value.
At PHP level, the raw payload remains available through `LineItem::getPayload()`, `LineItem::getPayloadValue()`, and `OrderLineItemEntity::getPayload()`, but `LineItem::jsonSerialize()` and `OrderLineItemEntity::jsonSerialize()` omit protected purchase prices from API output.
Because the field is removed during JSON serialization, the change applies to existing and new orders without rewriting historical order payloads.

### Image CMS element no longer emits a default `min-height` outside cover mode

The `min-height` of the image CMS element (`cms_slot` of type `image`) is now only meaningful in the `cover` display mode. New image elements default to an empty `minHeight` instead of `340px`, the Administration clears the value when switching away from `cover`, and the Storefront only applies a `min-height` (falling back to `340px`) when the display mode is `cover`. This fixes a forced height being applied in the `standard` and `stretch` display modes.

For the Storefront this is purely a rendering fix. Headless and Composable Frontends that read `config.minHeight.value` from the Store API should gate the value on `config.displayMode.value === 'cover'`, because relying on the previous `340px` default in non-cover modes no longer reflects the rendered behaviour. Existing image elements keep their stored `minHeight`; only newly created elements use the new empty default.

### DAL write event listeners no longer expand API ACL requirements

DAL post-write events such as `EntityWrittenContainerEvent` and entity-specific `.written` events are now dispatched in system scope after Admin API and Sync API writes, while preserving the original context source.

This matters for plugins that subscribe to core write events and update their own entities as a side effect.
Previously, a listener on an event such as `product.written` still ran in the CRUD context of the triggering API request.
When that listener wrote an extension-owned entity, the API user also needed permissions for that extension entity, even though the submitted request only changed products.
Activating such a plugin could therefore change the required ACL permissions for existing Admin API or Sync API clients.

With this change, listener-side DAL writes are treated as trusted system-side follow-up work of the original write.
API consumers only need the privileges required for the submitted write payload; plugin-internal denormalization, synchronization, indexing, or bookkeeping writes performed from DAL write listeners no longer expand the caller's ACL requirements.

Extension authors can still inspect who triggered the write via `$event->getContext()->getSource()`.
If a listener intentionally wants to make a side effect depend on the triggering user or integration, it should check the source explicitly instead of relying on `$event->getContext()->getScope()` being `Context::USER_SCOPE`.
No adoption is required for normal write-event listeners; remove any extra API permission requirements that only existed to satisfy listener-internal entity writes.

Private media visibility is not implicitly widened by this change.
During DAL write-event dispatch, Shopware marks the context with `Context::SYSTEM_SCOPE_DAL_WRITE_EVENT` so private media searches still apply normal visibility restrictions.
If a listener intentionally needs private media access, wrap that specific read in `$context->scope(Context::SYSTEM_SCOPE, ...)`; explicit system-scope reads continue to opt in to private media visibility.

### Manage translation downloads via the Admin API

Translation management — previously only possible through the `translation:list`, `translation:install`, and `translation:update` CLI commands — is now available through the Admin API, so it can be driven from the Administration without shell access:

- `GET /api/_action/translation/list` — lists every configured locale, merging its local install state with the remote metadata (`{ total, items: [{ locale, name, lastUpdate, progress, updateAvailable, isPseudoLanguage }] }`). `lastUpdate` is the local install timestamp (`null` when not installed), while `progress` comes from the remote metadata (`null` when the remote source is unavailable).
- `GET /api/_action/translation/meta` — returns configuration metadata for the translation UI (`{ builtInLocales, communityTranslationsUrl, documentationUrlSnippetKey, completenessThreshold }`).
- `POST /api/_action/translation/install` — downloads and installs translations for the given `locales` (or all configured locales when `all` is `true`); created languages are activated unless `activate` is `false`. Returns `{ updated, skipped, unavailable }`, where `unavailable` lists requested locales that have no translation available.
- `POST /api/_action/translation/update` — updates all installed translations. Returns `{ updated, skipped, unavailable }`.
- `DELETE /api/_action/translation/{locale}` — removes the downloaded translation files and the metadata entry for a locale. The associated `language`, `locale`, and `snippet_set` records are left untouched and remain manageable through their regular entity endpoints.

The routes are guarded by the new `system:translation` ACL privilege (`read` for listing and metadata, `create` for install, `update` for update, `delete` for uninstall).

`install` and `update` process the requested locales synchronously during the request, downloading each locale's snippet files in turn. Installing many locales at once — in particular `all: true`, which covers every configured locale — can therefore take a while, and the operation is not atomic: if one locale fails, the locales processed before it remain installed.

Two events are dispatched from the underlying services (so they fire for both the Admin API and the `translation:*` CLI commands), giving extensions a targeted hook instead of having to filter generic DAL write events:

- `Shopware\Core\System\Snippet\Event\TranslationLoadedEvent` — after a locale's translations are downloaded and installed (carries the locale and the `Context`).
- `Shopware\Core\System\Snippet\Event\TranslationRemovedEvent` — after a locale's downloaded files and metadata entry are removed (carries the locale).

### Download media files via the Admin API

The Admin API provides `GET /api/_action/media/{mediaId}/download` to download the binary file of a media entity.
Depending on the configured media storage and download strategy, the route may either stream the file from Shopware or respond with a redirect to the resolved download URL.

For Administration clients that need to decide whether to trigger a direct browser download or fall back to an authenticated blob request, the Admin API now also provides `GET /api/_action/media/{mediaId}/download/prepare`.
The route is guarded by the existing `media:read` ACL privilege and returns a small JSON payload describing whether the client should use an external URL or perform the authenticated blob download through Shopware.

## App System

### App permissions restrict Extension SDK requests and Administration modules

Extension SDK action and URI-signing requests now require `app.all` or `app.<appName>` ACL rights for the selected app.
Target URLs must be absolute and use a host declared in the app manifest's `allowed-hosts`.
The Administration module response omits modules for apps the current user cannot access.
Assign the relevant app privilege to users or integrations that need to use an app's Administration features, and keep the app's target hosts declared in its manifest.

### Deprecation of inline `<custom-fields>` in `manifest.xml`

Defining custom fields inline in `manifest.xml` via the `<custom-fields>` element is deprecated. Use a separate `Resources/config/custom-fields.xml` file instead. The inline definition will be removed in v6.8.0.

When an app has a `Resources/config/custom-fields.xml` file, it takes priority over the inline manifest definition. If only the inline definition exists, a deprecation warning is triggered.

### Tax provider priority is preserved across app updates

An app tax provider's `priority` is now only seeded from the manifest when the provider is first installed. App updates no longer touch the priority, so the merchant's manual ordering is retained.

## Hosting & Configuration

### New `GENERATE_SOURCEMAPS` environment variable for production builds

A new `GENERATE_SOURCEMAPS` environment variable controls whether JavaScript sourcemaps are emitted during production builds. Set it to `true` to generate sourcemaps when `NODE_ENV=production`; omit it or set it to any other value to keep the default behaviour (no sourcemaps in production).

This applies to the Storefront webpack build and all Vite builds (Administration core, Administration extension plugins, and Storefront components).

In non-production environments sourcemaps are always generated regardless of this variable.

```bash
GENERATE_SOURCEMAPS=true NODE_ENV=production composer build:js:admin
GENERATE_SOURCEMAPS=true NODE_ENV=production composer build:js:storefront
```

## Administration

### Opt-in TypeScript and ESLint configuration for Administration extensions (experimental)

This toolchain is **experimental** and not covered by the backwards-compatibility promise: it is shipped early to gather feedback while it is still being shaped, so the command names and their options, the layout of the generated files, and the `manifest.json` schema can change in any release without a deprecation cycle. Re-running setup after a Shopware update is the supported migration path — the generated files are disposable by design, so nothing should be hand-edited or automated on top of. Stabilization is targeted for v6.8.0 (annotated `@experimental stableVersion:v6.8.0 feature:ADMIN_EXTENSION_TOOLING` in the source); the experimental marker is removed once the surfaces have settled. Not running the command leaves a project exactly as it was.

`composer admin:setup-extension-tooling` generates the TypeScript and ESLint configuration that lets an Administration extension be type-checked and linted against the **installed** Shopware version — its live types, its entity schema and its pinned tool versions — instead of against a toolchain the extension vendors itself and that drifts.

- **Every extension is bridged automatically.** Setup discovers every installed extension with Administration sources from `var/plugins.json` and writes a git-ignored, self-explaining `.shopware/` bridge beside each Administration folder — custom and vendor-installed alike — plus small committable `tsconfig.json` / `eslint.config.mjs` that extend it when the extension has none yet. Existing configs are never overwritten — the report prints the single line to add instead. One bridge is written per directory that owns a config, so a multi-bundle package whose shared config governs several Administration roots gets a single shared bridge (`-- --root-config=<Extension>:<dir>` forces one for a layout the grouping cannot infer). Root `tsconfig.json` / `eslint.config.mjs` projections and IDE bootstraps for VS Code and Zed are generated at the project root (PhpStorm settings are printed); editors then resolve the full Administration API, including installation-specific entity types.
- **Unwritable directories degrade gracefully.** A bridge that cannot be written (e.g. a read-only vendor directory) is skipped with a warning; the extension's sources stay covered by the generated root `tsconfig.json`, which includes whatever no extension config governs. Files written under `vendor/` are local only: a composer update removes them and re-running setup restores them.
- **The report says what is missing, not just what happened.** Each extension is classified `ready`, `bridged`, `bridge-unwired` or `not bridged`, changed files are listed by path, git-ignored bridge files are distinguished from committable plugin-owned ones, and only the actually-missing next step is printed.
- **CI can verify the projection is current** with `composer admin:setup-extension-tooling:check` — a guaranteed read-only dry run that writes nothing and exits 1 on drift.

Options belong after Composer's `--` separator; `-- --help` prints the generated reference, and an unknown flag exits 2 before anything is written. In a Composer/Flex install, where the Administration lives under `vendor/shopware/administration` and the Composer scripts do not exist, the same commands are available as `bin/console administration:setup-extension-tooling` and `bin/console administration:generate-entity-schema-types` — run `npm ci` in the Administration directory once, since its Node dependencies are not part of the Composer package.

`composer admin:check-extensions` then runs that configuration: per extension it executes `vue-tsc` and ESLint with the Administration's own pinned tool versions and prints their native, unmodified output under a per-extension header, paths relativized to the project root. Pass `-- --only=<name[,name]>` to narrow it; without that it checks everything. Extension configs that do not compose the Shopware preset are visibly skipped with a reason-specific `why:` and the exact `fix:` — never silently green — and a run that skipped a writable extension carries a prominent warning so `exit 0` cannot be mistaken for full coverage. TypeScript is blocked with the fix command while the entity schema is missing, rather than flooding the report with cascade errors, and a JavaScript-only extension reports an honest qualified pass (`0 TypeScript files — .js is not type-checked`) instead of a vacuous green. `-- --fix` applies ESLint autofixes, including the Shopware `sw-*` → `mt-*` deprecation codemods. Findings in `vendor/`-installed extensions are reported but never fail the run.

Because bridging a large plugin can surface hundreds of findings at once — which would make an exit-1 check useless — `-- --only=<name> --update-baseline` records the current findings into a committed, plugin-relative `.shopware-admin-baseline.json` (custom/plugins only). The check then reports `N new · M baselined` and fails only on new findings, so a fully baselined plugin reads green while regressions still fail. Matching ignores line and column, so a recorded finding survives unrelated line drift; entries that no longer occur are reported as stale and pruned on the next `--update-baseline`. A safety net compares the structured parse against each tool's own counter and disables suppression on any disagreement, so a parser bug can never silently green a real finding. Diagnostics originating outside the extension's own root — a conflict it imposes on the shared type surface — are fatal and cannot be baselined away.

Spec files are type-checked too. A dedicated `vue-tsc` program per extension injects jest types (`describe`/`it`/`expect`, from a shipped `spec-types.d.ts`) and includes only the specs, so the runtime program stays free of runner globals; spec findings are reported on their own `TS (specs)` line and column. ESLint lints specs with the jest globals available, but its type-aware rules stay off for them: the generated `.shopware/` bridges are not solution-style project references, so typescript-eslint's project service has no spec program to resolve them against — `vue-tsc` is what type-checks specs, ESLint is not. Type-checking test code surfaces findings that were previously invisible, which is exactly what the baseline above absorbs: the two features are partners.

In an interactive terminal the check opens a numbered picker (numbers, ranges, `a` for all, `w` for writable-only); `-- --only=<name[,name]>` or `-- --all` skip it, and non-interactive shells check everything. Three flags exist for CI and debugging: `-- --fail-on-skipped` turns any skipped or blocked writable extension into exit 1, so `exit 0` cannot mean "checked nothing" — this is the flag a CI gate should use; `-- --strict-vendor` makes findings in `vendor/`-installed extensions fatal too; and `-- --show-commands` prints the exact underlying `vue-tsc`/ESLint invocation per extension, so any result can be reproduced by hand.

Nothing changes for existing flows: no default build, watch, init or CI pipeline invokes the new command. The full reference — flags, generated-file ownership, troubleshooting — lives in [`extension-tooling/README.md`](src/Administration/Resources/app/administration/extension-tooling/README.md).
Nothing changes for existing flows unless you opt in: in a platform checkout `composer setup` runs the setup command only when the experimental `ADMIN_EXTENSION_TOOLING` feature flag is enabled (e.g. `ADMIN_EXTENSION_TOOLING=1` in `.env`); without the flag the step is a one-line no-op, and no build, watch or CI pipeline invokes the command. The full reference — flags, generated-file ownership, troubleshooting — lives in [`extension-tooling/README.md`](src/Administration/Resources/app/administration/extension-tooling/README.md).

### Reworked search behaviour options

The "Search behaviour" card in `Settings > Search` presents the search mode as "Broad search (OR)" and "Exact search (AND)" with short one-line descriptions, replacing the previous "OR"/"AND" labels with example texts. The broad option is now listed first; the stored configuration (`product_search_config.andLogic`) and the template blocks are unchanged. Extensions that override the mode selection (e.g. Advanced Search) can swap the offered options based on their own configuration.

### Digital product upload validation uses backend private media metadata

Administration upload validation for digital products now derives private upload MIME metadata from the effective backend private extension allowlist. Extensions added through `shopware.filesystem.private_allowed_extensions` or `MediaFileExtensionWhitelistEvent` are reflected in `/api/_info/config` and in the digital-product upload UI.

### Snippet inheritance from JSON language files

The snippet detail page (`Settings > Snippets`) now indicates if a snippet is defined in a JSON language file and if it has been changed, displays its original value. Additionally, editors can now restore inheritance from the underlying JSON file.

Clicking the "restore inheritance" icon on an overridden field marks the database record for deletion upon saving. This allows the snippet to fall back to the JSON file value and ensures it stays synchronized with any future updates made to the language file.

### Block additions and renamings

Due to missing blocks and inappropriate block names, the following templates have received new blocks and/or contain blocks which have been deprecated and will be removed in v6.8.0. Use the respective replacements instead:

#### sw-cms-el-config-buy-box.html.twig

Deprecated -> Replacement:

* `sw_cms_element_buy_box_config_product_variant_label` -> `sw_cms_element_buy_box_config_product_selection_label`
* `sw_entity_single_select_base_results_list_result_label` -> `sw_cms_element_buy_box_config_product_select_result_item_inner`

#### sw-cms-el-config-cross-selling.html.twig

Deprecated -> Replacement:

* `sw_entity_single_select_variant_selected_item` -> `sw_cms_element_cross_selling_config_content_products_selection_label`
* `sw_entity_single_select_variant_result_item` -> `sw_cms_element_cross_selling_config_content_products_select_result_item`
* `sw_entity_single_select_base_results_list_result_label` -> `sw_cms_element_cross_selling_config_content_products_select_result_item_inner`

#### sw-cms-el-config-product-box.html.twig

Added:

* `sw_cms_element_product_box_config_product_selection_label`
* `sw_cms_element_product_box_config_product_select_result_item`

Deprecated -> Replacement:

* `sw_entity_single_select_base_results_list_result_label` -> `sw_cms_element_product_box_config_product_select_result_item_inner`

#### sw-cms-el-config-product-description-reviews.html.twig

Deprecated -> Replacement:

* `sw_entity_single_select_variant_selected_item` -> `sw_cms_element_product_description_reviews_config_product_selection_label`
* `sw_entity_single_select_variant_result_item` -> `sw_cms_element_product_description_reviews_config_product_select_result_item`
* `sw_entity_single_select_base_results_list_result_label` -> `sw_cms_element_product_description_reviews_config_product_select_result_item_inner`

#### sw-cms-el-config-product-slider.html.twig

Added:

* `sw_cms_element_product_slider_config_content_products_selection_label`
* `sw_cms_element_product_slider_config_content_products_select_result_item`

Deprecated -> Replacement:

* `sw_entity_single_select_base_results_list_result_label` -> `sw_cms_element_product_slider_config_content_products_select_result_item_inner`

#### sw-product-cross-selling-assignment.html.twig

Added:

* `sw_product_cross_selling_assignment_select_result_item`

Deprecated -> Replacement:

* `sw_entity_single_select_base_results_list_result_label` -> `sw_product_cross_selling_assignment_select_result_item_inner`

# 6.7.12.0

## Features

### Agentic files for sales channels

Sales channels can now publish opt-in agentic files such as `/llms.txt` and `/agents.md`.
Merchants can manage these files in the sales channel details, enable them per sales channel, preview the generated content, and add custom notes without replacing the default content provided by Shopware or extensions.

Core ships the initial `agentic` file family.
The files are rendered from Twig templates, so Shopware, plugins, apps, and themes can contribute or extend content through normal Shopware Twig inheritance.
Extensions can add additional templates under `Resources/views/files/agentic/**.twig`, including nested paths such as `.well-known/*`.

The Admin API exposes documented action routes for listing, reading details, and previewing sales-channel files under `/api/_action/sales-channel-file/{fileFamily}/{salesChannelId}`.

## Storefront

### Optional email fields no longer fail validation when empty

The `FormValidation` helper now treats empty values as valid for the email validator. This aligns with standard `<input type="email">` behavior, ensuring optional email fields are no longer marked invalid when left blank. Fields with the required rule remain unaffected.

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

### Use Bootstrap variable for headings spacing

The hard-coded CSS `margin-bottom` was removed from HTML headlines H1-H6. The bootstrap variable `$headings-margin-bottom` can now be used to set the bottom spacing of headlines.

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

### Customer email uniqueness is enforced on writes

Admin API and Sync API customer writes now enforce the same non-guest customer email uniqueness rules as the Administration customer form and Store API registration flows.
When `core.systemWideLoginRegistration.isCustomerBoundToSalesChannel` is disabled, a non-guest customer email must be unique globally.
When the setting is enabled, duplicate non-guest emails are only accepted for customers bound to different sales channels.
Extensions can use `Shopware\Core\Checkout\Customer\Validation\CustomerEmailUniqueChecker` with `CustomerEmailUniqueCheck` to apply the same configuration-aware uniqueness rules.

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

### Filter DAL entity write results with a typed collection

`EntityWrittenEvent::getResults()` now returns an `EntityWriteResultCollection` that keeps each payload associated with its operation and primary key.
Extension listeners can use `only()` to select write operations, `withPayloadProperties()` to select results containing any of the given payload properties, and `getPrimaryKeys()` to extract the filtered identifiers.
`EntityWrittenContainerEvent::getResults(string $entityName)` provides the same API for one entity in a container event.
The existing `getWriteResults()` methods remain unchanged.

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

### Core Twig `config()` helper

The Twig `config()` helper is now provided by the core Twig environment so non-storefront templates can read sales-channel-aware system configuration.
Twig templates can continue using `config('my.config.key')` unchanged.

The PHP methods `Shopware\Storefront\Framework\Twig\Extension\ConfigExtension::config()` and `Shopware\Storefront\Framework\Twig\TemplateConfigAccessor::config()` are deprecated.
PHP code should use `SystemConfigService` directly instead.

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

### `cache:watch:delayed` shuts down gracefully

The `cache:watch:delayed` command now stops cleanly on `SIGINT`/`SIGTERM` instead of being killed mid-loop, and exposes a configurable `--interval` option (microseconds) for the poll frequency.

### New `sha256` Twig filter

A new `sha256` Twig filter is available alongside the existing `md5` filter. Both accept strings and arrays (arrays are JSON-encoded before hashing) and return the hex-encoded hash.

### Variants can now be searched by parent product name

The new `parent.name` search field allows variants to be found through their parent product name and ranked independently from the variant's own `name`.

The field is disabled by default. Enable `parent.name` in the product search configuration to make this behavior active and adjust its ranking there.

### Reduced product data in listings via description teaser

Product listings can now load a shortened, HTML-free excerpt of the product description instead of the full text, which significantly reduces database load, transfer size and memory usage for catalogs with large descriptions. Previously the complete description was loaded for every product box even though the storefront only displays a few clamped lines.

This reduced loading is **enabled for fresh installations** and **disabled for existing shops**, which keep the full listing loading on update and can opt in per sales channel via the new `core.listing.partialDataLoading` setting (Settings > Products). When enabled, the product listing route loads a curated, reduced field set covering the default product boxes; listing products are then partial entities. Only enable it if your theme and extensions work with the reduced product data in listings.

A new read-only, translatable `descriptionTeaser` field is available on `product` (and `product_translation`). It is derived from the description on write (HTML stripped, truncated to 512 characters) and exposed via the Store and Admin API. The stripping is configurable through the `html_sanitizer` field set `product_translation.descriptionTeaser`. Existing products are backfilled asynchronously: the migration schedules the `product.description_teaser.indexer`, which runs over the message queue after the update (or manually via `bin/console dal:refresh:index`).

### Agentic Commerce product export and tracking classes deprecated

The following classes related to Agentic Commerce product exports, providers, and sales channel tracking are deprecated and will be removed in Shopware 6.8.0:

- `Shopware\Core\Content\ProductExport\Provider\AbstractAgenticCommerceProductExportProvider`
- `Shopware\Core\Content\ProductExport\Provider\AgenticCommerceProductExportProviderRegistry`
- `Shopware\Core\Content\ProductExport\Provider\GoogleProductExportProvider`
- `Shopware\Core\Content\ProductExport\Provider\OpenAiProductExportProvider`
- `Shopware\Core\Content\ProductExport\Validator\JsonlRowParser`
- `Shopware\Core\Content\ProductExport\Validator\OpenAiProductExportValidator`
- `Shopware\Core\Content\ProductExport\Validator\GoogleProductExportValidator`

This functionality will be available in the **Agentic Commerce extension (SwagAgenticCommerce)** instead.

## Administration

### `sw-select-field` forwards `aria-label` to the native select

The deprecated `sw-select-field` now passes through `aria-label` and `aria-labelledby` attributes to the underlying native `<select>` element. Previously these attributes were applied to the wrapping field component but never reached the form control, leaving selects without an accessible name (e.g. the range picker of `sw-chart-card`). Plugins that render a `sw-select-field` without a visible `<label>` can now give it an accessible name by passing `aria-label` / `aria-labelledby`.

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

### Rule builder shows per-field errors on conditions

Invalid condition inputs are now highlighted individually with a list of the affected fields below the row. All reversed date ranges are reported in one save instead of one at a time.

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

### Agentic Commerce Administration components deprecated

The following Administration component, methods, and computed properties are deprecated and will be removed in Shopware 6.8:

**`sw-agentic-commerce-tracking-config`** — entire component deprecated.

**`sw-sales-channel-modal-grid`:**
- computed `salesChannelRepository`
- method `isAgenticCommerceSalesChannelType()`
- method `showAgenticCommerceType()`

**`sw-sales-channel-detail`:**
- provide `swSalesChannelDetailGetAgenticCommerceExportConfig`
- data `agenticCommerceExportConfig`
- computed `isAgenticCommerce`
- computed `defaultAgenticCommerceExportConfig`
- method `validateAgenticCommerceExportConfig()`
- method `loadAgenticCommerceExportConfig()`
- method `saveAgenticCommerceExportConfig()`

**`sw-sales-channel-create-base`:**
- method `prefillAgenticCommerceDefaults()`

**`sw-sales-channel-detail-base`:**
- inject `swSalesChannelDetailGetAgenticCommerceExportConfig`
- computed `isAgenticCommerce`
- computed `resolvedAgenticCommerceExportConfig`
- method `getAgenticCommerceExportElementBind()`
- method `getAgenticCommerceExportCardTitle()`
- method `getAgenticCommerceExportCardPositionIdentifier()`
- method `onAgenticCommerceExportFieldUpdate()`

**`sw-sales-channel-detail-product-comparison`:**
- computed `isAgenticCommerce`

This functionality will be available in the **Agentic Commerce extension (SwagAgenticCommerce)** instead.

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

### [Experimental] Store API MCP server

A new MCP endpoint at `/store-api/_mcp` accepts standard Store API credentials
(`sw-access-key` + `sw-context-token`) and runs a separate capability registry from
the Admin API endpoint.

Plugins register Store API capabilities via service tags:
`shopware.store_api_mcp.tool`, `shopware.store_api_mcp.prompt`, `shopware.store_api_mcp.resource`.

Browser-based MCP clients are supported: the `mcp-session-id` and `mcp-protocol-version`
headers are allowed and exposed through CORS on API responses.

Both `McpContextProvider` and `StoreApiMcpContextProvider` implement `McpContextProviderInterface`.
Type-hint against the interface in tools that need to work across both API scopes.

Note: endpoint discovery and storefront session authentication are not included. Those
are provided by the UCP SDK.

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

### Store API breadcrumbs include non-navigable folder categories

`GET /store-api/breadcrumb/{id}` now includes folder categories in the returned hierarchy. Folder entries use `type: "folder"`, an empty `path`, and no SEO URLs.

Headless storefronts and integrations must not assume every breadcrumb item is navigable; render folder entries as labels.

### SEO URL paths reject characters that are not URL-allowed on write

Writes to `seo_url.seoPathInfo` now reject strings containing sequences that are not allowed in URLs: a stray `%` that does not form a valid percent-escape (`%XX`), the fragment marker `#`, backslashes, or ASCII control characters (`\x00`–`\x1F`, `\x7F`).
Query strings (`path?foo=bar`) and valid percent-escapes (`caf%C3%A9`) remain allowed — they are URL-valid and resolvable by the SEO resolver.
The validation runs in three places backed by a single rule (`ValidSeoPathInfo::containsDisallowedCharacters`):

* the admin `POST /api/_action/seo-url/create-custom-url` and `PATCH /api/_action/seo-url/canonical` endpoints (via `SeoUrlValidationFactory`),
* raw `POST /api/seo-url` DAL writes (via a new `PreWriteValidationEvent` subscriber, `SeoUrlWriteValidator`),
* and the inline admin UI form (`sw-seo-url`).

API consumers that currently persist any of these sequences in `seoPathInfo` will receive a `CONTENT__SEO_URL_INVALID_CHARACTERS` violation where the write previously succeeded.
Percent-encode the value (`%25` for a literal `%`) or sanitise it on the client side before sending.

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

### Axios upgrade with dual-client dispatcher

The Administration now supports axios 1.x alongside the existing axios 0.30.2 to address security vulnerability CVE-2023-45857. A dual-client dispatcher pattern has been implemented that allows both versions to coexist, enabling a gradual migration path for plugins and custom code.

**Current behavior (6.7.x):**
- Default: axios 0.30.2 (backward compatible)
- Opt-in: Add `useAxiosV1: true` to request configuration to use axios 1.x
- Repository requests use axios 1.x internally. Their transport is not configurable through repository options because repositories do not expose axios as part of their public contract.
- Existing `httpClient.interceptors` and `httpClient.defaults` customizations are mirrored to both internal clients, so extensions do not need version-specific setup.
- The Shopware HTTP client remains structurally compatible with the previous `AxiosInstance` type and `axios-mock-adapter`, while new code can use Shopware's `HttpClient` contract.

**Future behavior (6.8.0+):**
- Direct HTTP request default: axios 1.x (when `V6_8_0_0` feature flag is active)
- Direct HTTP request opt-out: Add `useAxiosV1: false` if axios 0.30.2 is still needed

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
