# ReplaceElement

The one operation that changes an element's type in place, and the only one whose contract is a set of carry-over
rules rather than a placement. The other operations are in [operations.md](operations.md).

## What it carries over

Swaps an element's component to `$newType`, keeping the same id. `requireRegistered($newType)` must pass; the element must
exist (`mutationTargetNotFound`). The swap carries over a property whose key the new type declares with an
enforceable type and whose value that type admits (`PropertyType::enforceableTypes()` / `admits()`, the rule the
write gate and the diagnostics apply, so a null and an all-primitive union both carry). It also carries wiring (data
requirements, providers, consumers) keyed to a new-type property with no enforceable type (`object`, an FQCN, or a
union carrying either), and children of slots present in the new type. It seeds the new type's stored defaults for
uncarried keys (a carried or authored value wins). Ordinary wiring keyed to a lone primitive or an all-primitive union is dropped and reported in `droppedWiring()`. Typed mapping consumers are handled separately through source-aware admission, as described below.
A key the new type declares as `object` or an FQCN has no enforceable type, so nothing can vouch for the
value's shape and it is dropped rather than carried across unexamined. The element's `style` carries over
unconditionally, being universal and type-independent, and
`attributedSpecifications` survives only for keys whose carried data requirement survives.

A stored property under one of the new type's `resolvedBy` storage keys is likewise carryable. `carryProperties()`
maps the new type's default specification's `resolves` entries whose loader is one of the two built-in resolvedBy
loaders (`Binding/ResolvedByLoaderBranch`) to their storage keys. It carries a value forward
only when its shape strictly matches that branch (a string for `entity`, a list of strings for `entity_collection`). A shape mismatch is dropped and reported like any other uncarryable value.

With a bound source, whole-field mapping consumers survive only when they remain valid for the new type; invalid mappings are removed and reported as dropped wiring. An intentionally unbound tree drops mapping consumers without source-specific inspection, while compatible authored properties remain. Inline tokens remain authored property text rather than consumer wiring.

Mapping cleanup applies only to the replaced element. Carried descendants keep their mapping consumers, and their mapping problems are reported against those descendants by normal diagnostics. A descendant's finding cannot remove a same-named consumer from the replacement or add a dropped-wiring entry for it.

## The default overlay

After the rebuild, the new type's default binding specification is fill-applied after the
wiring carry-over when it has exactly one. Zero is a no-op. More than one throws `bindingSpecificationDefaultAmbiguous` `409`.
Carried wiring is therefore never overwritten by the default even when the default would correct a
renamed storage key.

## Result channels

[`orphaned`](../README.md#result-channels) = children of slots absent from the new type. `droppedWiring` = old wiring keys not carried.
`droppedProperties` = static property values whose key is absent from the new type (and not a carryable `resolvedBy`
storage key) or whose value its property type rejects. A value rejected for a key the new type still declares as a
primitive with a default is reported as dropped even though that default then re-fills the key. A key absent from
the new type is reported and never re-filled, because the default overlay is keyed only by the new type's primitive
keys. `affected = subtreeIds($replacement)`; `created = [$elementId]` only, because the carried-over children keep
their own nodes while the replaced node is re-scaffolded under the same id.

`freshlyScaffolded = [$elementId]` makes only the replacement eligible for default mappings and consumer mirroring; retained descendants keep their authored state. Mapping admission reads `StoredTree::$rootSource`.
