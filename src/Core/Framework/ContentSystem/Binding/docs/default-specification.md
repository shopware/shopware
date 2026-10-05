# The Default Specification

`BindingSpecification::isDefault()` derives as `id === type`: computed on read, never stored, the same pattern `qualifiedId()` uses. `toSchema()` emits `default: bool` accordingly. The equality holds exactly for a specification `DefaultBindingSpecificationSynthesizer` produced from a type's `resolvedBy` properties (see [resolved-by.md](resolved-by.md)). Its `id` is set to the type name itself. That id is reserved, so no authored `bindings:` entry may use it.

At most one default exists per type. These mechanisms guarantee it, not a runtime check:

- Type names are globally unique across sources.
- A synthesized specification's type is always its own containing file's type, so only the owning source can synthesize it.
- The app persister and validator resolve app types through the app's own overlay only.
- The dev/prod loader split means an app's specifications come from exactly one loader per environment.

A database row created outside the app lifecycle that fakes `id === type` for a foreign type is the one case these mechanisms do not prevent. Its effect splits on whether that type already has a legitimate default. Where one coexists, the fake collides into the ambiguity throw below (`bindingSpecificationDefaultAmbiguous`, 409). Where the type has no legitimate default, the lone fake *is* the sole default the application-time read finds. It is silently fill-applied as the type's default with no legitimacy check. It is otherwise indistinguishable from a legitimate specification.

At application time, `InsertElement` fill-applies a fresh element's type default. `ReplaceElement` fill-applies the new type's default after carrying wiring over. The default set for a type (`byType(type)` filtered by `isDefault()`) is read as zero, one, or more. Zero is a no-op. One is fill-applied. More than one throws `ContentSystemException::bindingSpecificationDefaultAmbiguous` (409, naming the type plus the colliding qualified ids), per [failure-and-loss.md](../../docs/principles/failure-and-loss.md#a-component-throws-where-it-meets-invalid-data-and-nothing-degrades-silently).

Fill-only application (`BindingApplicator::applyFillOnly()`) and its layering under an explicit choice: [applying.md](applying.md).

A non-default specification is applied only through `bind-element` or an explicit `bindingSpecificationId`: [applying.md](applying.md).

## The core defaults

Core ships no dedicated binding-specification directory and no authored inline `bindings:` entry. Every core binding specification is therefore a synthesized default, each from the `resolvedBy` properties of one file under `Layout/Type/Definitions/`:

- `core:Sw:Media:Image` — `media` from the `mediaId` storage key, `media/image.yaml`
- `core:Sw:Grid:Container` — `backgroundImage` from `backgroundImageId`, `grid/container.yaml`
- `core:Sw:Media:Youtube` — `previewMedia` from `previewMediaId`, `media/youtube.yaml`
- `core:Sw:Media:Vimeo` — `previewMedia` from `previewMediaId`, `media/vimeo.yaml`
- `core:Sw:Media:Gallery` — `mediaItems` from `mediaIds`, `media/gallery.yaml`
- `core:Sw:Product:Slider` — `products` from `productIds`, `product/slider.yaml`
- `core:Sw:Navigation:Tree` — `navigationTree`, `navigation/tree.yaml`

The first four wire the `entity` loader, their properties each being a `MediaEntity`. `Sw:Media:Gallery` and `Sw:Product:Slider` wire `entity_collection`, their properties being a `MediaCollection` and a `SalesChannelProductCollection`. `Sw:Navigation:Tree` is the odd one. Its `resolvedBy` is a tier-B loader block (`navigation: {rootId: main-navigation}`) rather than a bare storage key. It therefore wires the `navigation` loader and names no storage key at all.

## Overriding a core default

A plugin cannot override a *core* default; no replacement mechanism exists.
