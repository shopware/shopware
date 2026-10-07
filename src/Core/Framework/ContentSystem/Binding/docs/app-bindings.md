# App Bindings

How an app's binding specifications reach storage and the registry, and what validates them on the way.

`App/Aggregate/AppContentSystemBindingSpecification/AppContentSystemBindingSpecificationDefinition` (DAL entity, table `app_content_system_binding_specification`, `UNIQUE (app_id, name)` — bindings are unique only within their app, unlike the globally unique style options, so two apps may legitimately ship the same bare id), `App/Lifecycle/Persister/ContentSystemBindingSpecificationPersister` (loads the app's inline `bindings:` sections and its `resolvedBy`-synthesized specifications via `loadDtosFromTypeDirectory`, canonicalizing against a type overlay built from the app's own types — see [inline-bindings.md](inline-bindings.md#inline-bindings-in-an-app-the-type-overlay); hash-based upsert/delete in one transaction, registry invalidation only after a committed change), `App/Lifecycle/Handler/ContentSystemBindingSpecificationLifecycleHandler` (persists on install/update; invalidates the registry on activate/deactivate/uninstall/delete), and `App/Validation/ContentSystemBindingSpecificationAppValidator` (manifest-time schema validation of the app's inline `bindings:` sections and synthesized specifications, turning every `ContentSystemException` — canonicalization failure, unknown type, load failure, reserved id, including a `resolvedBy` FQCN that derives ambiguously or to no registered entity — into a `ContentSystemBindingSpecificationSchemaError` rather than an exception; DB-unique-key collisions stay the loader's job; its constructor is the loader plus the type loader only, no registry dependency).

## The Type Overlay

The app persister and validator pass a type overlay built from the app's own `Resources/content-system/types`. Every non-app path passes an empty overlay — both `YamlBindingSpecificationLoader::load()`, the compiler-pass scan, and the `DatabaseBindingSpecificationLoader` rows.

## Reading Them Back

`DatabaseBindingSpecificationLoader` deserializes all active-app rows and validates them together, keyed by `source:id` so equal bare ids from different apps stay distinct. A missing name, or a schema that fails to decode, deserialize or validate, aborts the whole load — the same fail-fast posture `YamlBindingSpecificationLoader` has. Exceptions thrown by the validator itself propagate out of `load()` unwrapped.

## Storage

`Migration/V6_7/Migration1782423128AddAppContentSystemBindingSpecificationTable` creates `app_content_system_binding_specification`: `id`, `app_id` (FK, `ON DELETE CASCADE`), `name`, `schema` (JSON), `hash`, timestamps. `App/Validation/Error/ContentSystemBindingSpecificationSchemaError` is the error type the manifest validator reports through.
