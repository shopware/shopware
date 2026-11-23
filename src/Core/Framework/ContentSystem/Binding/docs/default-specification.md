# The Default Specification

`BindingSpecification::isDefault()` derives as `id === type`: computed on read, never stored, the same pattern `qualifiedId()` uses. `toSchema()` emits `default: bool` accordingly. The equality holds exactly for a specification `DefaultBindingSpecificationSynthesizer` produced from a type's `resolvedBy` properties (see [resolved-by.md](resolved-by.md)). Its `id` is set to the type name itself. That id is reserved, so no authored `bindings:` entry may use it.

At most one default exists per type. These mechanisms guarantee it, not a runtime check:

- Type names are globally unique across sources.
- A synthesized specification's type is always its own containing file's type, so only the owning source can synthesize it.
- The app persister and validator resolve app types through the app's own overlay only.
- The dev/prod loader split means an app's specifications come from exactly one loader per environment.

A database row created outside the app lifecycle that fakes `id === type` for a foreign type is the one case these mechanisms do not prevent. Its effect splits on whether that type already has a legitimate default. Where one coexists, the fake collides into the ambiguity throw below (`bindingSpecificationDefaultAmbiguous`, 409). Where the type has no legitimate default, the lone fake *is* the sole default the application-time read finds. It is silently fill-applied as the type's default with no legitimacy check. It is otherwise indistinguishable from a legitimate specification.

At application time, `InsertElement` fill-applies a fresh element's type default. `ReplaceElement` fill-applies the new type's default after carrying wiring over. `AttachElement` fill-applies each element's type default across the attached subtree. The default set for a type (`byType(type)` filtered by `isDefault()`) is read as zero, one, or more. Zero is a no-op. One is fill-applied. More than one throws `ContentSystemException::bindingSpecificationDefaultAmbiguous` (409, naming the type plus the colliding qualified ids), per [failure-and-loss.md](../../docs/principles/failure-and-loss.md#a-component-throws-where-it-meets-invalid-data-and-nothing-degrades-silently).

Fill-only application (`BindingApplicator::applyFillOnly()`) and its layering under an explicit choice: [applying.md](applying.md).

A non-default specification is applied only through `bind-element` or an explicit `bindingSpecificationId`: [applying.md](applying.md).

## The core defaults

Core ships no dedicated binding-specification directory and no authored inline `bindings:` entry. Every core binding specification is therefore synthesized, each from the `resolvedBy` properties of one file under `Layout/Type/Definitions/`. Two examples:

- `core:Sw:Media:Image`: `media` from the `mediaId` storage key, `media/image.yaml`
- `core:Sw:Navigation:Breadcrumb`: `breadcrumb`, `navigation/breadcrumb.yaml`

`Sw:Media:Image` wires the `entity` loader, its property being a `MediaEntity`. `Sw:Navigation:Breadcrumb` names no storage key, because its `resolvedBy` is a tier-B loader block. That block wires the `breadcrumb` loader, its `property` and `type` config values `!scoped` over the `product` and `category` root sources ([resolved-by.md](resolved-by.md)).

## Overriding a core default

A plugin cannot override a *core* default, because no replacement mechanism exists.

## Limits on a Synthesized Id

`DefaultBindingSpecificationSynthesizer` enforces `MAX_ID_LENGTH = 255` on the minted id, which is the type name, and applies the storage-key collision rules as hard load errors that name the offending file.
