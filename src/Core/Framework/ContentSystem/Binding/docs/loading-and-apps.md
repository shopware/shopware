# Loading, Registry, App Integration

How binding specifications reach the registry: the loading tiers, the registry, and the compiler pass that discovers them. The app lifecycle around them is in [app-bindings.md](app-bindings.md).

Three loading tiers, one registry, discovered by one compiler pass — the same shape as `Layout/Element/Style/`:

1. **Loading** (`Loader/`) — both loaders extend `AbstractContentSystemBindingSpecificationLoader`. `YamlBindingSpecificationLoader` handles core, bundle, and plugin bindings in every environment plus app bindings in dev; its one load method, `loadDtosFromTypeDirectory(directory, source, prefix, typeOverlay = [])`, scans an element-type directory's `*.yaml`/`*.yml` files. Every file passes through the injected `DefaultBindingSpecificationSynthesizer`, whether or not it carries a `bindings:` key: a file with at least one `resolvedBy` property registers a synthesized default specification (see [resolved-by.md](resolved-by.md)), whose id joins the same per-directory id set an authored entry populates, so a collision with an authored id surfaces as the same duplicate error. The optional top-level `bindings:` map is then loaded as before, each entry a specification whose type is implicit (see [inline-bindings.md](inline-bindings.md)). It deserializes each entry via `Serialization/BindingSpecificationSerializer`, validates the DTOs, and deduplicates within and across directories (`ContentSystemException::bindingSpecificationDuplicate`). `DatabaseBindingSpecificationLoader` loads active app bindings from `app_content_system_binding_specification` in prod and returns empty in dev, mirroring `DatabaseStyleOptionLoader`. The loader's `$directories` are `Layout/Type/Loader/ElementTypeSourceDirectory` instances carrying `source`, `path`, and `prefix` (all non-nullable strings) per directory — the same directory VO the type loader takes, since both scan the element-type directories; `ResolvedBindingSpecificationDto` bridges loading and specification creation.
2. **Registry** (`Registry/`) — the Shopware decoration pattern. `AbstractContentSystemBindingSpecificationRegistry` defines the contract: `all()` (keyed by source-qualified id, `source:id`), `byType(type)`, `get(qualifiedId)`, and `invalidate()`. `ContentSystemBindingSpecificationRegistry` is the stateless aggregator (leaf) over loaders tagged `content_system.binding_specification_loader` (parallel to `content_system.style_option_loader`); `CachedContentSystemBindingSpecificationRegistry` decorates it with a `cache.system` pool under the cache key `content_system.binding_specifications`.

3. **Compiler Pass** — `Framework/DependencyInjection/CompilerPass/ContentSystemCompilerPass` is the single pass that discovers the element-type directories and injects them into both the type loader and `YamlBindingSpecificationLoader` (each loader gets its own directory-VO definition instances); the binding loader scans those same directories' files for their inline `bindings:` sections. The directory set is core `Layout/Type/Definitions` (prefix `Sw`), each non-plugin bundle's `Resources/content-system/types` (prefix `Sw`), each active plugin's `Plugin::getContentTypeDirectory()` (prefix = plugin name), and (dev only) each active app's `Resources/content-system/types` (prefix = app name).

## What Core Ships

Core ships no dedicated binding-specification directory and no authored inline `bindings:` entry. Every core binding specification is a synthesized default — six in all, each from the `resolvedBy` properties of one file under `Layout/Type/Definitions/`:

| Specification | Property from storage key | File | Loader |
|---|---|---|---|
| `core:Sw:Media:Image` | `media` from `mediaId` | `media/image.yaml` | `entity` |
| `core:Sw:Grid:Container` | `backgroundImage` from `backgroundImageId` | `grid/container.yaml` | `entity` |
| `core:Sw:Media:Youtube` | `previewMedia` from `previewMediaId` | `media/youtube.yaml` | `entity` |
| `core:Sw:Media:Vimeo` | `previewMedia` from `previewMediaId` | `media/vimeo.yaml` | `entity` |
| `core:Sw:Media:Gallery` | `mediaItems` from `mediaIds` | `media/gallery.yaml` | `entity_collection` |
| `core:Sw:Navigation:Tree` | `navigationTree` | `navigation/tree.yaml` | `navigation` |

`Sw:Media:Gallery` uses `entity_collection` because its property is a `MediaCollection` rather than a `MediaEntity`. `Sw:Navigation:Tree` is the one whose `resolvedBy` is a tier-B loader block (`navigation: {rootId: main-navigation}`) rather than a bare storage key — it wires the `navigation` loader and names no storage key at all.
