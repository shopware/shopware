# The Default Specification

`BindingSpecification::isDefault()` derives as `id === type`: computed on read, never stored, the same pattern `qualifiedId()` uses. `toSchema()` emits `default: bool` accordingly. The equality holds exactly for a specification `DefaultBindingSpecificationSynthesizer` produced from a type's `resolvedBy` properties (see [resolved-by.md](resolved-by.md)): its `id` is set to the type name itself, and that id is reserved, so no authored `bindings:` entry may use it.

At most one default exists per type, guaranteed by these mechanisms rather than a runtime check: type names are globally unique across sources; a synthesized specification's type is always its own containing file's type, so only the owning source can synthesize it; the app persister and validator resolve app types through the app's own overlay only; and the dev/prod loader split means an app's specifications come from exactly one loader per environment. A database row created outside the app lifecycle that fakes `id === type` for a foreign type is the one case these mechanisms do not prevent, and its effect splits on whether that type already has a legitimate default: where one coexists, the fake collides into the ambiguity throw below (`bindingSpecificationDefaultAmbiguous`, 409); where the type has no legitimate default, the lone fake *is* the sole default the application-time read finds, and is silently fill-applied as the type's default with no legitimacy check. It is otherwise indistinguishable from a legitimate specification.

At application time (`InsertElement` fill-applying a fresh element's type default, `ReplaceElement` fill-applying the new type's default after carrying wiring over) the default set for a type (`byType(type)` filtered by `isDefault()`) is read as zero, one, or more: zero is a no-op, one is fill-applied, more than one throws `ContentSystemException::bindingSpecificationDefaultAmbiguous` (409, naming the type plus the colliding qualified ids), per [failure-and-loss.md](../../docs/principles/failure-and-loss.md#a-component-throws-where-it-meets-invalid-data-and-nothing-degrades-silently).

Fill-only application (`BindingApplicator::applyFillOnly()`) and its layering under an explicit choice: [applying.md](applying.md).

A non-default specification is applied only through `bind-element` or an explicit `bindingSpecificationId`: [applying.md](applying.md).

## The core defaults

Core ships no dedicated binding-specification directory and no authored inline `bindings:` entry, so every core binding specification is a synthesized default, each from the `resolvedBy` properties of one file under `Layout/Type/Definitions/`:

- `core:Sw:Media:Image` — `media` from the `mediaId` storage key, `media/image.yaml`
- `core:Sw:Grid:Container` — `backgroundImage` from `backgroundImageId`, `grid/container.yaml`
- `core:Sw:Media:Youtube` — `previewMedia` from `previewMediaId`, `media/youtube.yaml`
- `core:Sw:Media:Vimeo` — `previewMedia` from `previewMediaId`, `media/vimeo.yaml`
- `core:Sw:Media:Gallery` — `mediaItems` from `mediaIds`, `media/gallery.yaml`
- `core:Sw:Product:Slider` — `products` from `productIds`, `product/slider.yaml`
- `core:Sw:Navigation:Tree` — `navigationTree`, `navigation/tree.yaml`

The first four wire the `entity` loader, their properties each being a `MediaEntity`. `Sw:Media:Gallery` and `Sw:Product:Slider` wire `entity_collection`, their properties being a `MediaCollection` and a `SalesChannelProductCollection`. `Sw:Navigation:Tree` is the odd one: its `resolvedBy` is a tier-B loader block (`navigation: {rootId: main-navigation}`) rather than a bare storage key, so it wires the `navigation` loader and names no storage key at all.

## Overriding a core default

A plugin cannot override a *core* default; no replacement mechanism exists.
