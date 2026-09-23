---
title: Generic storage for manifest-declared app features
date: 2026-07-13
area: framework
tags: [app, manifest, storage, lifecycle]
---

## Context

Every capability an app declares in its manifest needs to be available at runtime without the manifest file, because for remote apps the extracted files only exist transiently (`Source/RemoteZip` downloads on demand). Today each capability solves storage individually, in one of two ways:

1. **A JSON/list column on the `app` row**: `modules`, `main_module`, `cookies`, `allowed_hosts`, `requested_privileges`, `source_config`. Each new capability of this kind adds a column to the `app` table and ad-hoc read code (for example `AppCookieCollectListener` querying the `cookies` column).
2. **A dedicated `app_*` table**: `app_action_button`, `app_flow_action`, `app_payment_method`, `app_mcp_tool`, and others. These tables exist because their content needs foreign keys to core entities, indexed lookups by another subsystem, per-item mutable state, or per-language translation rows.

Capabilities in the first category share the same shape: a list of named, typed items, written on install/update, read back as a whole, removed with the app. Each one re-implements parsing-to-column persistence and column-to-consumer reads. The `app` table gains a column per capability, and there is no common, typed way to read "the items of kind X declared by active apps". Upcoming features (for example app-provided consent definitions) would add another column and another reader.

Note: roughly a third of the existing manifest capabilities have this shape. The MCP feature alone added six tables (tools, prompts, resources, plus a translation table each) whose content would have fit here.

Update semantics are also solved per capability: persisters rewrite stored state from the manifest and discard changes made in the shop (eg tax handler ordering was lost on every app update). There is no shared answer to "the app declares X, the shop changed it to Y, the app updates".

## Decision

We will introduce one generic table for manifest-declared app data:

```sql
CREATE TABLE `app_feature` (
    `id` BINARY(16) NOT NULL,
    `app_id` BINARY(16) NULL,          -- null while rows are kept through an uninstall with keepUserData
    `app_name` VARCHAR(255) NOT NULL,  -- durable identity: app ids change across reinstalls, names do not
    `type` VARCHAR(64) NOT NULL,       -- feature kind, one per AppFeatureDefinition ('cookie', 'consent', ...)
    `name` VARCHAR(255) NOT NULL,      -- item within the kind, unique per app
    `payload` JSON NOT NULL,           -- item data, mapped by the definition
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `idx.app_feature.type` (`type`),
    CONSTRAINT `fk.app_feature.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `uniq.app_feature.app_name_type_name` UNIQUE (`app_name`, `type`, `name`)
);
```

One row per declared item, whether a kind has many items per app or a single one:

| app_name | type    | name          | payload |
|----------|---------|---------------|---------|
| MyApp    | cookie  | cart_tracking | `{...}` |
| MyApp    | cookie  | session_pref  | `{...}` |
| MyApp    | consent | data_sharing  | `{...}` |

Payloads are opaque JSON: validation happens at manifest parse time (XSD) and in the typed definition mapping, the same trust model as the existing JSON columns.

`keepUserData` is what the `app_name` column exists for: on uninstall with `keepUserData`, rows are kept and the foreign key sets their `app_id` to null. A later installation of the same app re-attaches them by app name, the same matching `PaymentMethodLifecycleHandler` uses to re-link payment methods after a reinstall. Without `keepUserData`, the lifecycle handler deletes the rows.

### Code API

The API will live in `Shopware\Core\Framework\App\Feature`, all `@internal` initially. Two contracts for feature owners:

```php
interface AppFeatureConfig
{
    public function getName(): string;    // identity within its type and app, stable across updates, eg: `cart_tracking`
}

/**
 * One implementation per feature kind, registered with the `shopware.app_feature.definition` DI tag.
 *
 * @template T of AppFeatureConfig
 */
interface AppFeatureDefinition
{
    public function getType(): string;                        // the `type` column value
    public function getConfigClass(): string;                 // class-string<T>
    public function extract(Manifest $manifest): array;       // list<T>
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array;
    public function fromPayload(array $payload): AppFeatureConfig;
}
```

Consumers read through `AppFeatureStorage`, the only entry point, and get typed results:

```php
/** @var list<AppFeature<Cookie>> $features */
$features = $storage->forActiveApps(Cookie::class);          // or forApp($appId, Cookie::class)
$features[0]->appName;                                       // declaring app
$features[0]->config->expiration;                            // the typed config
```

Deactivated apps keep their rows; `forActiveApps()` filters on `app.active`, matching how `cookies` and `requested_privileges` behave today.

Shop-side changes are written through `AppFeatureStorage::save($appId, $config)`, which stores the given config as is (the caller's state is authoritative) and requires the feature to be declared by the app. The app lifecycle syncs through `syncForApp()` via a regular `shopware.app_lifecycle.handler`.

### Reconciliation is owned by the definition

On app update the definition's `toPayload($declared, $stored)` receives both the newly declared config and the currently stored one (`$stored` is null on first install and for direct `save()` writes) and decides what is preserved and what is refreshed. There is no platform-level merge policy; the signature forces every feature author to make the decision explicitly. A complete definition:

```php
/** @implements AppFeatureDefinition<Cookie> */
class CookieFeatureDefinition implements AppFeatureDefinition
{
    public function getType(): string { return 'cookie'; }
    public function getConfigClass(): string { return Cookie::class; }

    public function extract(Manifest $manifest): array
    {
        return array_map(
            static fn (array $c) => new Cookie($c['cookie'], $c['snippet_name'], $c['value'] ?? null, $c['expiration'] ?? null),
            $manifest->getCookies()?->getCookies() ?? []
        );
    }

    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        return [
            'cookie' => $declared->cookie,
            'snippet_name' => $declared->snippetName,
            'value' => $declared->value,
            // the cookie lifetime is shop-owned: once stored, an app update does not reset it
            'expiration' => $stored->expiration ?? $declared->expiration,
        ];
    }

    public function fromPayload(array $payload): Cookie
    {
        return new Cookie($payload['cookie'], $payload['snippet_name'], $payload['value'] ?? null, $payload['expiration'] ?? null);
    }
}
```

Syncing diffs by `(type, name)`: payloads of surviving rows are rewritten in place (preserving `id` and `created_at`), new declarations are inserted, and rows the manifest no longer declares are deleted. `name` stability across app updates is therefore part of the feature contract.

Adding a new manifest-declared capability requires: the manifest XSD section and XML class (as today), a config DTO, and an `AppFeatureDefinition` implementation.

### Translations

Translatable values (labels, descriptions) stay in the payload as the locale-keyed maps the manifest already declares:

```json
{"name": "my_tool", "label": {"en-GB": "Tool", "de-DE": "Werkzeug"}}
```

This is how `modules` already stores its labels on the `app` row today; the Admin resolves the map at render time. Resolution happens at read time: walk `Context::getLanguageIdChain()`, map each language to its locale code via the existing `LanguageLocaleCodeProvider`, and return the first entry present in the map, falling back to the app's default-locale value (the manifest parser guarantees that entry exists). There is no translation table: per-language rows created at install would only exist for languages present at install, while the locale map resolves against whatever languages exist at read time.

## Scope

`app_feature` is intended for simple features: manifest-declared items that are read back as "all items of one type", with shop-side modifications living inside the payload. Features that need more — foreign keys to core entities, state that cannot live in the payload, or indexed lookups by payload content — are likely better served by a dedicated table, as payment methods are today. That is a judgement call per feature, not a rule.

Existing JSON columns stay where they are. `AppEntity` getters for `cookies`, `modules`, and the other columns are consumed by downstream projects; migrating them requires a deprecation cycle and is only worthwhile at a major. New capabilities start on `app_feature`; existing ones can follow later.

## Consequences

- The `app` table stops growing a column per capability; the storage and lifecycle code for manifest data exists once.
- On every app update each definition is handed the stored state next to the new declaration and decides what survives.
- Feature owners get typed reads through `AppFeatureStorage` instead of ad-hoc queries against JSON columns.
- The data is not exposed through the auto-generated Admin API. Capabilities that need to surface their data do so through their own endpoints.
- All classes in `Shopware\Core\Framework\App\Feature` are `@internal` initially. The surface can be promoted to a supported extension point once the first consumers have settled the contract.
