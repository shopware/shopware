# Custom Binding Specifications

The plugin- and app-facing authoring guide: where a specification lives, how it is registered, and how it is discovered.

A binding specification is a pre-validated data wiring for one element type: a `resolves` map wiring the type's reference properties to data loaders, plus `inputs` defaults for its primitive properties. An editor (or an agentic layout builder) applies one to an element in a single action (the `bind-element` mutation, or an `insert-element` request carrying a `bindingSpecificationId`) instead of hand-assembling loader configs.

The simplest case needs no authored specification at all: declaring `resolvedBy` on a reference property (see [Custom Element Types](../../Layout/Type/docs/custom-types.md)) synthesizes a default specification for the type automatically, fill-applied to every freshly inserted or replaced element of that type with no client-side binding step. Plugins and apps additionally author specifications inline, in the optional top-level `bindings:` key of an element-type YAML file — for an alternative or additional wiring beyond the type's default.

`resolvedBy` names the storage key the element stores the referenced id under. A typo in that key is not caught at load time — an undeclared storage key is indistinguishable from an intentional one — and instead surfaces later as an unfilled required input when the layout is diagnosed.

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

Tier A is closed: a bare string resolves only against the reference property's declared FQCN, to the built-in `entity` or `entity_collection` loader — a subclass of `Entity` or `EntityCollection` respectively, nothing else. Sugar never resolves by precedence: an entry that cannot expand deterministically (a tier-A reference FQCN that is neither an `Entity` nor an `EntityCollection` subclass, several entities producing the same class, an unknown tier-B config key) is a load-time error whose message names the fix. `inputs` entries are synthesized automatically for every primitive property the wiring reads, and every input carries a derived `required` flag (set when the property is read through a required config key and the wired reference property is itself required). Expansion rules in detail: [authoring-sugar.md](authoring-sugar.md).

## Collision Detection

Uniqueness is per source, not global: a duplicate bare id within one source is a load-time error, while two different sources may ship the same bare id. The registry keys specifications by their source-qualified id (`source:id`), which is also the wire identifier clients pass back as `bindingSpecificationId`. This is intentionally looser than the style-option system's flat global namespace: a binding is scoped to the element type it declares, not a Store-API wire key.

## App Lifecycle

App specifications are persisted to `app_content_system_binding_specification` on install/update and cascade-deleted with the app; the registry is invalidated on activate/deactivate/uninstall/delete. Because an app is inactive at install time, its own types are not yet registered; validation resolves the declared type against a type overlay built from the app's own type files. Because the type is always the containing element type, an app binding can only ever target one of the app's own types.

## Discoverability

A registered specification appears folded under a `bindingSpecifications` key per type entry in `GET /api/_info/content-system-element-types.json`. A client derives the specifications applicable to an element from `bindingSpecifications[element.component]`. See [introspection.md](introspection.md).

Reference: [../README.md](../README.md), `Layout/Type/Definitions/media/image.yaml` (core `resolvedBy` example)
