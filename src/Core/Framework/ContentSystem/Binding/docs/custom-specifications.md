# Custom Binding Specifications

A binding specification is a pre-validated data wiring for one element type: a `resolves` map wiring the type's reference properties to data loaders, plus `inputs` defaults for its primitive properties. An editor (or an agentic layout builder) applies one to an element in a single action (the `bind-element` mutation, or an `insert-element` request carrying a `bindingSpecificationId`) instead of hand-assembling loader configs.

The simplest case needs no authored specification at all: declaring `resolvedBy` on a reference property (see [Custom Element Types](../../Layout/Type/docs/custom-types.md)) synthesizes a default specification for the type automatically, fill-applied to every freshly inserted or replaced element of that type with no client-side binding step. Plugins and apps additionally author specifications inline, in the optional top-level `bindings:` key of an element-type YAML file — for an alternative or additional wiring beyond the type's default.

`resolvedBy` storage key and the typo case that surfaces at diagnosis: [resolved-by.md](resolved-by.md).

## Registration

| Source | Directory                                     |
|--------|------------------------------------------------|
| Plugin | Types directory (`getContentTypeDirectory()`) |
| App    | `Resources/content-system/types`              |

The compiler pass discovers plugin YAML automatically, scanning the same types directory the element-type system uses. App YAML is validated at manifest time and persisted on install/update; in production, app bindings load from the database. No service registration needed.

## Authoring Sugar

A `resolves` entry accepts three shapes; the first two are expanded to the canonical third at load time:

| Tier | Shape                                                                      | When to use                                                     |
|------|----------------------------------------------------------------------------|-----------------------------------------------------------------|
| A    | `media: mediaId` (bare property-reference string)                          | The property's declared FQCN is an `Entity`/`EntityCollection` subclass |
| B    | `media: { entity: { property: mediaId } }` (single key names the loader)   | Name the loader explicitly; entity names are derived            |
| C    | `media: { loader: entity, config: { entity: media, property: mediaId } }`  | Canonical form; the only shape for unusual configs              |

`inputs` entries are synthesized automatically for every primitive property the wiring reads, and every input carries a derived `required` flag (set when the property is read through a required config key and the wired reference property is itself required). Tier A closure and the load-time errors: [authoring-sugar.md](authoring-sugar.md).

## Collision Detection

Uniqueness is per source, not global: a duplicate bare id within one source is a load-time error, while two different sources may ship the same bare id. The registry keys specifications by their source-qualified id (`source:id`), which is also the wire identifier clients pass back as `bindingSpecificationId`. This is intentionally looser than the style-option system's flat global namespace: a binding is scoped to the element type it declares, not a Store-API wire key.

## App Lifecycle

App specifications are persisted to `app_content_system_binding_specification` on install/update and cascade-deleted with the app; the registry is invalidated on activate/deactivate/uninstall/delete. An app binding validates against a type overlay built from the app's own types, so it can only target one of them: [inline-bindings.md](inline-bindings.md#inline-bindings-in-an-app-the-type-overlay).

## Discoverability

A registered specification appears folded under a `bindingSpecifications` key per type entry in `GET /api/_info/content-system-element-types.json`. See [introspection.md](introspection.md).

Reference: [../README.md](../README.md), `Layout/Type/Definitions/media/image.yaml` (core `resolvedBy` example)
