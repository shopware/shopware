# ReplaceElement

The one operation that changes an element's type in place, and the only one whose contract is a set of carry-over
rules rather than a placement. The other operations are in [operations.md](operations.md).

## What it carries over

Swaps an element's component to `$newType`, keeping the same id. `requireRegistered($newType)` must pass; the element must
exist (`mutationTargetNotFound`). The swap carries over primitive properties whose key and type match, wiring (data
requirements, providers, consumers) keyed to a non-primitive new-type property, and children of slots present in the
new type. It seeds the new type's primitive defaults for uncarried keys (a carried or authored value
wins). The element's `style` carries over unconditionally, being universal and type-independent, and
`attributedSpecifications` survives only for keys whose carried data requirement survives.

A stored property under one of the new type's `resolvedBy` storage keys is likewise carryable. `carryProperties()`
maps the new type's default specification's `resolves` entries whose loader is one of the two built-in resolvedBy
loaders (`Binding/ResolvedByLoaderBranch`) to their storage keys. It carries a value forward
only when its shape strictly matches that branch (a string for `entity`, a list of strings for `entity_collection`). A shape mismatch is dropped and reported like any other uncarryable value.

## The default overlay

After the rebuild, the new type's default binding specification is fill-applied after the
wiring carry-over when it has exactly one. Zero is a no-op. More than one throws `bindingSpecificationDefaultAmbiguous` `409`.
Carried wiring is therefore never overwritten by the default even when the default would correct a
renamed storage key.

## Result channels

`orphaned` = children of slots absent from the new type. `droppedWiring` = old wiring keys minus kept.
`droppedProperties` = static property values whose key is absent from the new type (and not a carryable `resolvedBy`
storage key) or whose value its property type rejects. A value rejected for a key the new type still declares as a
primitive with a default is reported as dropped even though that default then re-fills the key. A key absent from
the new type is reported and never re-filled, because the default overlay is keyed only by the new type's primitive
keys. `affected = subtreeIds($replacement)`; `created = [$elementId]` only, because the carried-over children keep
their own nodes while the replaced node is re-scaffolded under the same id.
