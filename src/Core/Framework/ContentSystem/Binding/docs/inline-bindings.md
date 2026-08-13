# Inline `bindings:` in Element-Type Files

Where an authored specification lives, how its type and id are derived from the containing file, and what an app needs on top.

A binding specification is authored inline in its element type's YAML file, so a simple element ships as one file. The optional top-level `bindings:` key is a map of bare specification id → entry; the element-type serializer ignores unknown top-level keys, so the section is invisible to the type pipeline (no type-loader change).

- The **type is implicit** — the containing file's type name, resolved from the file path plus the directory prefix by the same `Layout/Type/Loader/ElementTypeNameResolver` the type loader uses (a file `media/image.yaml` under prefix `Sw` yields type `Sw:Media:Image`). An entry declaring an explicit `type:` is a load-time error (`bindingSpecificationCanonicalizationFailed`); so is an explicit `id:` (the map key is the id, a divergent inner copy would silently drift).
- Each entry is fed through the **same denormalize → canonicalize → validate path** (`['type' => <implicit>] + <entry>`), so the sugar tiers and every other facet behave the same for every entry. A `bindings:` value that is not a map, or an entry that is not a map, is a hard load error (`bindingSpecificationLoadFailed`) naming the file. Files without a `bindings:` key are skipped.
- **Uniqueness stays bare-id-per-source** (a flat namespace across all of a source's types): two inline entries with the same id, whether in one type file or across two type files of one source, throw `bindingSpecificationDuplicate`. Two different sources may still each ship the same bare id.

The **YAML authoring key is `bindings:`** (shorthand inside the type file); the introspection **wire key is `bindingSpecifications`** (the folded catalog on `content-system-element-types.json`). The entries are specifications, not bindings — a type has no bindings, its elements do; see the naming note in [NAMING.md](../../NAMING.md).

## Inline bindings in an app: the type overlay

An inline binding's implicit type is always the containing element type — for an app, one of the app's own types. But canonicalization (and the semantic validator) require the declared type to be *registered*, and an app is installed **inactive**, so at install and at `manifest:validate` time neither `DatabaseTypeLoader` (`WHERE active = 1`) nor the compiler pass (dev, active apps only) surfaces the app's own types yet. Without help, every app inline binding on the app's own type would fail with `bindingSpecificationUnknownType`.

The app persister and app validator close this by building a **type overlay** from the app's own types (`YamlTypeLoader::loadOverlayFromDirectory()` on the app's own `Resources/content-system/types` with the app's own name as prefix, keyed by resolved type name) and passing it to both load calls. `BindingSpecificationCanonicalizer::canonicalize()` and the collection's `TypeConsistentBindingSpecification` resolve the declared type **overlay-first, then registry**. Every non-app path passes an empty overlay and is unchanged; DB-sourced rows validate with an empty overlay because the app is active by the time its rows are read.

Because an inline binding's implicit type is always its own containing element type, an app binding can only ever target one of the app's own types — never another app's — so the overlay built from the app's own type files always covers it; there is no cross-app resolution gap. When an app's own type file is malformed, the two soft/hard boundaries diverge: the validator's `buildTypeOverlay` falls back to an empty overlay so the binding surfaces as `bindingSpecificationUnknownType` (a schema error, not an exception), while the persister fails the install with a wrapped `AppException` before the binding load runs.

## Inline `bindings:` in a Type File

A specification for a type you own lives in that type's YAML file, so a simple element ships as one file. The optional top-level `bindings:` key maps bare specification id → entry. The type is implicit (the containing file), so an entry declaring its own `type:` or `id:` is a load-time error. A type with a `resolvedBy` reference already gets its default specification synthesized (see [custom-specifications.md](custom-specifications.md)); a `bindings:` entry adds an alternative or additional wiring for the same property, for example one that also loads an association:

```yaml
# MyPlugin/Resources/content-system/types/product/quick-view.yaml
properties:
  relatedProduct:
    type: Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity
    resolvedBy: relatedProductId

bindings:
  with-media-association:
    label: "Related product (with media)"
    resolves:
      relatedProduct:
        entity:
          property: relatedProductId
          associations: [media]
```

The map key (`with-media-association` above) is the specification's id — any id except the type's own name, which is reserved for the `resolvedBy`-synthesized default (a load-time `bindingSpecificationReservedId` error for authored YAML claiming it). Each entry declares:

- **`label`** (required): a human label.
- **`resolves`** (optional): reference property key → loader wiring. Three authoring shapes, see [Authoring Sugar](custom-specifications.md#authoring-sugar). Every key must name a property the implicit type actually declares.
- **`inputs`** (optional): primitive property key → `{ default: ... }`. The default seeds the property when the specification is applied, only if the element does not already carry a value. Do not author `required`; the flag is derived (see [Authoring Sugar](custom-specifications.md#authoring-sugar)), and declaring it by hand is a load-time error.
